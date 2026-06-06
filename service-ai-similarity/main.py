from fastapi import FastAPI, HTTPException, BackgroundTasks
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import httpx
import os
from qdrant_client import QdrantClient
from qdrant_client.http import models
import logging

# Configuration
OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://ollama:11434")
QDRANT_HOST = os.getenv("QDRANT_HOST", "qdrant")
QDRANT_PORT = int(os.getenv("QDRANT_PORT", 6333))
COLLECTION_NAME = "incidents"
EMBEDDING_MODEL = "nomic-embed-text"
LLM_MODEL = os.getenv("LLM_MODEL", "phi3:mini")

app = FastAPI(title="Oriotel AI Similarity Service")
qdrant = QdrantClient(host=QDRANT_HOST, port=QDRANT_PORT)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

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

            return AnalysisResult(**data)
        except Exception as e:
            logger.error(f"Error getting analysis from Ollama: {e}")
            # Fallback values to avoid 500 error in indexing
            return AnalysisResult(
                priorite="Normal",
                scorePriorite=0.5,
                resume=text[:100]
            )

@app.on_event("startup")
async def startup_event():
    # S'assurer que les modèles sont téléchargés dans Ollama
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

    # S'assurer que la collection Qdrant existe
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
