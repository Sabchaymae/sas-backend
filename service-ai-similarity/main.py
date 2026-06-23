from fastapi import FastAPI, HTTPException, BackgroundTasks
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import httpx
import os
import asyncio
from qdrant_client import QdrantClient
from qdrant_client.http import models
import logging
# Initialize logger FIRST before anything else!
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

try:
    from zk import ZK, const
    from zk_service import ZKService
    ZK_AVAILABLE = True
except (ImportError, ModuleNotFoundError):
    ZK_AVAILABLE = False
    logger.warning("ZK library not available - ZKTeco features disabled")

from ai_anomaly import AttendanceAI
from prometheus_client import Counter, Histogram, generate_latest, CONTENT_TYPE_LATEST
from fastapi.responses import Response
import time

# Configuration
OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://ollama:11434")
QDRANT_HOST = os.getenv("QDRANT_HOST", "qdrant")
QDRANT_PORT = int(os.getenv("QDRANT_PORT", 6333))
COLLECTION_NAME = "incidents"
EMBEDDING_MODEL = "nomic-embed-text"
LLM_MODEL = os.getenv("LLM_MODEL", "phi3:mini")
OPERATIONS_SERVICE_URL = os.getenv("OPERATIONS_SERVICE_URL", "http://service-operations:8000")
ZK_DEVICE_IP = os.getenv("ZK_DEVICE_IP", "192.168.1.201")

app = FastAPI(title="Oriotel AI Similarity Service")
qdrant = QdrantClient(host=QDRANT_HOST, port=QDRANT_PORT)

zk_service = None
if ZK_AVAILABLE:
    zk_service = ZKService(ip=ZK_DEVICE_IP)

attendance_ai = AttendanceAI()

# Nombre total de requêtes IA
AI_REQUESTS = Counter(
    "ai_requests_total",
    "Nombre total de requêtes traitées"
)

# Nombre de tickets analysés
AI_TICKETS_ANALYZED = Counter(
    "ai_tickets_analyzed_total",
    "Nombre de tickets analysés"
)

# Temps d'inférence Ollama
AI_INFERENCE_TIME = Histogram(
    "ai_inference_duration_seconds",
    "Temps de réponse d'Ollama"
)

# Erreurs IA
AI_ERRORS = Counter(
    "ai_errors_total",
    "Nombre total d'erreurs"
)

# --- Pydantic Models ---
class SyncUserInput(BaseModel):
    user_id: str
    name: str
    department: Optional[str] = None

class AttendanceInput(BaseModel):
    attendance_id: int
    employee_id: int
    date: str
    clock_in: str
    clock_out: Optional[str] = None

# --- Background Task: Polling ZKTeco ---
async def poll_zkteco():
    if not ZK_AVAILABLE or not zk_service:
        logger.warning("Polling ZKTeco skipped - ZK library not available")
        return
    """Poll ZKTeco terminal for new attendance logs every 60 seconds"""
    while True:
        try:
            logger.info("Polling ZKTeco terminal for new logs...")
            logs = zk_service.get_attendance_logs()
            if logs:
                async with httpx.AsyncClient() as client:
                    for log in logs:
                        # Send to Laravel
                        await client.post(
                            f"{OPERATIONS_SERVICE_URL}/api/v1/attendance",
                            json={
                                "user_id": log['user_id'],
                                "timestamp": log['timestamp'],
                                "device_id": 1 # Assume device 1 for now
                            }
                        )
            logger.info(f"Processed {len(logs)} logs from terminal")
        except Exception as e:
            logger.error(f"Error polling ZKTeco: {e}")
        
        await asyncio.sleep(60)

@app.get("/metrics")
async def metrics():
    return Response(generate_latest(), media_type=CONTENT_TYPE_LATEST)

@app.on_event("startup")
async def startup_event():
    # 1. Start the ZKTeco polling task
    asyncio.create_task(poll_zkteco())
    
    # 2. S'assurer que les modèles sont téléchargés dans Ollama
    models_to_pull = [EMBEDDING_MODEL, LLM_MODEL]
    async with httpx.AsyncClient(timeout=None) as client: # Disable timeout for pulls
        for model in models_to_pull:
            try:
                logger.info(f"Checking if model {model} is available in Ollama...")
                response = await client.post(
                    f"{OLLAMA_HOST}/api/pull",
                    json={"name": model, "stream": False}
                )
                if response.status_code == 200:
                    logger.info(f"Model {model} is ready.")
                else:
                    logger.error(f"Failed to pull model {model}: {response.text}")
            except Exception as e:
                logger.error(f"Could not connect to Ollama to pull model {model}: {e}")

    # 3. S'assurer que la collection Qdrant existe
    try:
        collections = qdrant.get_collections().collections
        exists = any(c.name == COLLECTION_NAME for c in collections)
        if not exists:
            logger.info(f"Creating collection {COLLECTION_NAME}")
            # nomic-embed-text utilise 768 dimensions
            qdrant.create_collection(
                collection_name=COLLECTION_NAME,
                vectors_config=models.VectorParams(size=768, distance=models.Distance.COSINE),
            )
    except Exception as e:
        logger.error(f"Failed to initialize Qdrant: {e}")

# --- API Endpoints ---
@app.post("/sync-user")
async def sync_user(data: SyncUserInput):
    if not ZK_AVAILABLE or not zk_service:
        raise HTTPException(status_code=503, detail="ZKTeco integration disabled")
    success = zk_service.sync_user(data.user_id, data.name, data.department)
    if not success:
        raise HTTPException(status_code=500, detail="Failed to sync user with terminal")
    return {"status": "success", "message": f"User {data.user_id} synced"}

@app.post("/analyze-attendance")
async def analyze_attendance(data: AttendanceInput):
    # Detect anomalies using AI
    # In a real scenario, we'd fetch history for this employee_id first
    history = [] 
    
    anomalies = attendance_ai.analyze_record(
        {'clock_in': data.clock_in, 'clock_out': data.clock_out, 'date': data.date},
        history
    )
    
    if anomalies:
        async with httpx.AsyncClient() as client:
            for anomaly in anomalies:
                try:
                    await client.post(
                        f"{OPERATIONS_SERVICE_URL}/api/v1/anomalies",
                        json={
                            "employee_id": data.employee_id,
                            "date": data.date,
                            "type": anomaly['type'],
                            "description": anomaly['description'],
                            "severity": anomaly['severity'],
                            "status": "pending"
                        }
                    )
                except Exception as e:
                    logger.error(f"Failed to report anomaly: {e}")
    
    return {"status": "success", "anomalies_detected": len(anomalies)}


class IncidentInput(BaseModel):
    id: int
    text: str
    metadata: Dict[str, Any] = {}

class SearchQuery(BaseModel):
    text: str
    city: Optional[str] = None
    limit: int = 5
    threshold: float = 0.5

class AnalysisResult(BaseModel):
    site: Optional[str] = None
    equipement: Optional[str] = None
    categorie: Optional[str] = None
    serviceImpacte: Optional[str] = None
    priorite: str
    scorePriorite: float
    resume: str

async def get_embedding(text: str):
    async with httpx.AsyncClient(timeout=60.0) as client:
        try:
            response = await client.post(
                f"{OLLAMA_HOST}/api/embeddings",
                json={"model": EMBEDDING_MODEL, "prompt": text}
            )
            response.raise_for_status()
            return response.json()["embedding"]
        except Exception as e:
            logger.error(f"Error getting embedding from Ollama: {e}")
            raise HTTPException(status_code=500, detail="AI Service (Ollama) unavailable")

async def get_analysis(text: str) -> AnalysisResult:
    prompt = f"""Analyse ce ticket d'incident et réponds uniquement en JSON.
Ticket: "{text}"

JSON format attendu:
{{
  "site": "nom du lieu ou N/A",
  "equipement": "matériel concerné ou N/A",
  "categorie": "Réseau, Matériel, Logiciel ou Sécurité",
  "serviceImpacte": "Internet, VPN, Téléphone ou N/A",
  "priorite": "Critique, Important ou Normal",
  "scorePriorite": 0.9,
  "resume": "résumé très court"
}}"""
    start_time = time.time()
    async with httpx.AsyncClient(timeout=120.0) as client:
        try:
            # On demande explicitement du JSON dans le prompt et via le paramètre format
            response = await client.post(
                f"{OLLAMA_HOST}/api/generate",
                json={
                    "model": LLM_MODEL,
                    "prompt": prompt,
                    "stream": False,
                    "format": "json",
                    "options": {
                        "temperature": 0.1
                    }
                }
            )
            response.raise_for_status()
            res_json = response.json()
            raw_response = res_json.get("response", "").strip()
            
            # Nettoyage au cas où Ollama rajoute du texte
            import json
            try:
                data = json.loads(raw_response)
            except json.JSONDecodeError:
                # Fallback: essayer de trouver le JSON dans le texte
                import re
                match = re.search(r'\{.*\}', raw_response, re.DOTALL)
                if match:
                    data = json.loads(match.group())
                else:
                    raise ValueError(f"Could not parse JSON from response: {raw_response}")
            AI_TICKETS_ANALYZED.inc()
            AI_INFERENCE_TIME.observe(
               time.time() - start_time
)
            return AnalysisResult(**data)
        except Exception as e:
            logger.error(f"Error getting analysis from Ollama: {e}")
            # Fallback values to avoid 500 error in indexing
            return AnalysisResult(
                priorite="Normal",
                scorePriorite=0.5,
                resume=text[:100]
            )

class UserInput(BaseModel):
    id: int
    name: str
    role: str

class TaskInput(BaseModel):
    id: int
    title: str
    description: Optional[str] = ""
    priority: str
    due_date: Optional[str] = None
    status: str
    assigned_at: Optional[str] = None
    assigned_to: List[UserInput] = []

class OptimizationSuggestion(BaseModel):
    taskId: int
    taskTitle: str
    currentPriority: Optional[str] = None
    currentAssignee: Optional[str] = None
    suggestedAssignee: Optional[str] = None
    suggestedAssigneeId: Optional[int] = None
    action: str # reassign, prioritize, block_and_reassign, delay
    reason: str
    severity: str # critical, high, medium

class OptimizationResponse(BaseModel):
    reassignments: List[OptimizationSuggestion]
    deadlines: List[Dict[str, Any]]
    summary: Dict[str, Any]

class TaskOptimizationInput(BaseModel):
    users: List[UserInput]
    tasks: List[TaskInput]

@app.post("/tasks/optimize", response_model=OptimizationResponse)
async def optimize_tasks(input: TaskOptimizationInput):
    """
    Use Ollama to optimize the task list with user data.
    """
    # Préparer les données pour le prompt
    users_text = "\n".join([
        f"- ID: {u.id}, Nom: {u.name}, Rôle: {u.role}"
        for u in input.users
    ])
    
    tasks_text = "\n".join([
        f"- ID: {t.id}, Titre: {t.title}, Priorité: {t.priority}, Échéance: {t.due_date or 'N/A'}, Statut: {t.status}, Assigné à: {', '.join([u.name for u in t.assigned_to])}"
        for t in input.tasks
    ])

    prompt = f"""Tu es un chef de projet expert en optimisation de charge de travail. Analyse ces données et propose des optimisations.

RÈGLES IMPORTANTES:
1. Si une tâche URGENTE est en statut TO_DO et n'a pas été mise à jour depuis plus de 15 minutes, réaffecte-la à quelqu'un du MÊME RÔLE avec le moins de travail.
2. Équilibre la charge de travail entre les utilisateurs du même rôle.
3. Actions possibles: reassign, prioritize, block_and_reassign, delay.
4. Ne jamais supprimer de tâche.
5. Sois réaliste.
6. Réponds UNIQUEMENT en JSON valide, pas de texte supplémentaire.

UTILISATEURS DISPONIBLES:
{users_text}

LISTE DES TÂCHES:
{tasks_text}

Réponds UNIQUEMENT au format JSON suivant:
{{
  "reassignments": [
    {{
      "taskId": 1,
      "taskTitle": "Nom de la tâche",
      "currentPriority": "HAUTE",
      "currentAssignee": "Nom actuel",
      "suggestedAssignee": "Nom suggéré",
      "suggestedAssigneeId": 2,
      "reason": "Raison de la réaffectation",
      "action": "reassign",
      "severity": "medium"
    }}
  ],
  "deadlines": [
    {{
      "taskId": 1,
      "taskTitle": "Nom de la tâche",
      "oldDate": "2026-06-17",
      "newDate": "2026-06-18",
      "reason": "Raison"
    }}
  ],
  "summary": {{
    "mostLoadedUser": "Nom de l'utilisateur le plus chargé",
    "leastLoadedUser": "Nom de l'utilisateur le moins chargé",
    "urgentTasksCount": 0,
    "delayRiskCount": 0,
    "automaticReassignmentsCount": 0
  }}
}}"""

    async with httpx.AsyncClient(timeout=120.0) as client:
        try:
            response = await client.post(
                f"{OLLAMA_HOST}/api/generate",
                json={
                    "model": LLM_MODEL,
                    "prompt": prompt,
                    "stream": False,
                    "format": "json",
                    "options": {
                        "temperature": 0.1
                    }
                }
            )
            response.raise_for_status()
            res_json = response.json()
            raw_response = res_json.get("response", "").strip()
            
            import json
            try:
                data = json.loads(raw_response)
            except json.JSONDecodeError:
                # Nettoyer la réponse si nécessaire
                import re
                match = re.search(r'\{.*\}', raw_response, re.DOTALL)
                if match:
                    data = json.loads(match.group())
                else:
                    # Fallback si l'IA échoue
                    return get_fallback_optimization(input.users, input.tasks)
            
            # Vérifier que les champs sont présents
            if "reassignments" not in data:
                data["reassignments"] = []
            if "deadlines" not in data:
                data["deadlines"] = []
            if "summary" not in data:
                data["summary"] = {
                    "mostLoadedUser": None,
                    "leastLoadedUser": None,
                    "urgentTasksCount": 0,
                    "delayRiskCount": 0,
                    "automaticReassignmentsCount": 0
                }
            
            return OptimizationResponse(**data)
        except Exception as e:
            logger.error(f"Error optimizing tasks with Ollama: {e}")
            # Fallback sur la logique règle-based
            return get_fallback_optimization(input.users, input.tasks)


def get_fallback_optimization(users: List[UserInput], tasks: List[TaskInput]) -> OptimizationResponse:
    """
    Logique de fallback si Ollama n'est pas disponible.
    """
    # Calculer la charge de travail par utilisateur
    user_workload = {}
    for user in users:
        user_workload[user.id] = {
            "user": user,
            "score": 0,
            "tasks_count": 0
        }
    
    for task in tasks:
        for assigned_user in task.assigned_to:
            if assigned_user.id in user_workload:
                score = 0
                if task.priority == "URGENT": score = 5
                elif task.priority == "HIGH": score = 3
                elif task.priority == "MEDIUM": score = 2
                else: score = 1
                
                user_workload[assigned_user.id]["score"] += score
                user_workload[assigned_user.id]["tasks_count"] += 1
    
    # Trouver les tâches à réaffecter
    reassignments = []
    import datetime
    now = datetime.datetime.now(datetime.timezone.utc)
    
    for task in tasks:
        # Vérifier si la tâche est urgente et en timeout
        if task.priority == "URGENT" and task.status == "TO_DO" and task.assigned_at:
            try:
                assigned_at = datetime.datetime.fromisoformat(task.assigned_at.replace('Z', '+00:00'))
                time_diff = (now - assigned_at).total_seconds()
                if time_diff > 10:  # 10 seconds for testing
                    current_user = task.assigned_to[0] if task.assigned_to else None
                    if current_user:
                        # Trouver l'utilisateur du même rôle avec la charge la plus faible
                        same_role_users = [u for u in users if u.role == current_user.role and u.id != current_user.id]
                        if same_role_users:
                            best_user = min(same_role_users, key=lambda u: user_workload.get(u.id, {"score": 999})["score"])
                            reassignments.append(OptimizationSuggestion(
                                taskId=task.id,
                                taskTitle=task.title,
                                currentPriority=task.priority,
                                currentAssignee=current_user.name,
                                suggestedAssignee=best_user.name,
                                suggestedAssigneeId=best_user.id,
                                reason="Timeout 15min - Tâche Urgente (IA indisponible)",
                                action="block_and_reassign",
                                severity="critical"
                            ))
            except Exception as e:
                logger.warning(f"Error checking task timeout: {e}")
    
    # Summary
    sorted_workload = sorted(user_workload.values(), key=lambda x: x["score"], reverse=True)
    most_loaded = sorted_workload[0]["user"].name if sorted_workload else None
    least_loaded = sorted_workload[-1]["user"].name if sorted_workload else None
    urgent_count = sum(1 for t in tasks if t.priority == "URGENT")
    
    return OptimizationResponse(
        reassignments=reassignments,
        deadlines=[],
        summary={
            "mostLoadedUser": most_loaded,
            "leastLoadedUser": least_loaded,
            "urgentTasksCount": urgent_count,
            "delayRiskCount": len(reassignments),
            "automaticReassignmentsCount": 0
        }
    )

async def background_analysis_and_indexing(incident: IncidentInput, vector: List[float]):
    try:
        # Analyse par LLM
        analysis = await get_analysis(incident.text)
        logger.info(f"Analyse en arrière-plan terminée pour {incident.id}: {analysis}")
        
        # Indexation dans Qdrant avec les résultats de l'analyse
        qdrant.upsert(
            collection_name=COLLECTION_NAME,
            points=[
                models.PointStruct(
                    id=incident.id,
                    vector=vector,
                    payload={
                        **incident.metadata,
                        "text": incident.text,
                        "analysis": analysis.dict()
                    }
                )
            ]
        )
        logger.info(f"Incident {incident.id} indexé avec succès avec les données d'analyse.")
        
        # Ici, on pourrait appeler un webhook pour notifier l'application principale
        # await notify_main_app(incident.id, analysis)
        
    except Exception as e:
        logger.error(f"Erreur lors du traitement en arrière-plan pour {incident.id}: {e}")

@app.post("/ticket/process")
async def process_new_ticket(incident: IncidentInput, background_tasks: BackgroundTasks):
    """
    Endpoint principal pour traiter un nouveau ticket :
    1. Vérifie la similarité de manière synchrone (pour retour immédiat).
    2. Lance l'analyse LLM et l'indexation en arrière-plan.
    """
    logger.info(f"Traitement d'un nouveau ticket {incident.id}")
    try:
        # 1. Obtenir l'embedding pour la recherche
        vector = await get_embedding(incident.text)
        
        # 2. Recherche de similarité (Sync)
        try:
            if hasattr(qdrant, "query_points"):
                results = qdrant.query_points(
                    collection_name=COLLECTION_NAME,
                    query=vector,
                    limit=5,
                    score_threshold=0.30
                ).points
            else:
                results = qdrant.search(
                    collection_name=COLLECTION_NAME,
                    query_vector=vector,
                    limit=5,
                    score_threshold=0.30
                )
        except Exception as e:
            logger.error(f"Search failed: {e}")
            results = []
        
        # 3. Lancer le reste en arrière-plan
        background_tasks.add_task(background_analysis_and_indexing, incident, vector)
        
        return {
            "id": incident.id,
            "similar_incidents": [
                {
                    "id": res.id, 
                    "score": round(res.score, 4), 
                    "text": res.payload.get("text"),
                    "resume": res.payload.get("analysis", {}).get("resume", "N/A")
                } 
                for res in results
            ]
        }
    except Exception as e:
        logger.error(f"Erreur lors du traitement du ticket {incident.id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/index")
async def index_incident(incident: IncidentInput):
    logger.info(f"Indexing incident {incident.id}: {incident.text[:50]}...")
    try:
        vector = await get_embedding(incident.text)
        qdrant.upsert(
            collection_name=COLLECTION_NAME,
            points=[
                models.PointStruct(
                    id=incident.id,
                    vector=vector,
                    payload={**incident.metadata, "text": incident.text}
                )
            ]
        )
        return {"status": "success", "id": incident.id}
    except Exception as e:
        logger.error(f"Error indexing incident {incident.id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/analyze")
async def analyze_ticket(incident: IncidentInput):
    logger.info(f"Analyzing incident {incident.id}")
    try:
        analysis = await get_analysis(incident.text)
        return {
            "status": "success",
            "id": incident.id,
            "analysis": analysis
        }
    except Exception as e:
        logger.error(f"Error analyzing incident {incident.id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/search")
async def search_similar(query: SearchQuery):
    logger.info(f"Searching for similar incidents: {query.text[:50]}")
    try:
        vector = await get_embedding(query.text)
        
        # Search with threshold and limit
        try:
            if hasattr(qdrant, "query_points"):
                results = qdrant.query_points(
                    collection_name=COLLECTION_NAME,
                    query=vector,
                    limit=query.limit,
                    score_threshold=query.threshold
                ).points
            else:
                results = qdrant.search(
                    collection_name=COLLECTION_NAME,
                    query_vector=vector,
                    limit=query.limit,
                    score_threshold=query.threshold,
                    with_payload=True
                )
        except Exception as e:
            logger.error(f"Search endpoint failed: {e}")
            results = []

        logger.info(f"Found {len(results)} matches for query")
        
        # Format results as requested
        return {
            "query": query.text,
            "count": len(results),
            "matches": [
                {
                    "id": res.id,
                    "score": round(res.score, 4),
                    "text": res.payload.get("text"),
                    "metadata": {k: v for k, v in res.payload.items() if k != "text"}
                } for res in results
            ]
        }
    except Exception as e:
        logger.error(f"Error searching similar incidents: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
async def health():
    return {"status": "healthy"}

@app.get("/metrics")
async def metrics():
    return Response(
        generate_latest(),
        media_type=CONTENT_TYPE_LATEST
    )

