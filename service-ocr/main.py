import os
os.environ["FLAGS_enable_pir_api"] = "0"
os.environ["FLAGS_allocator_strategy"] = "naive_best_fit"

# pyrefly: ignore [import-error]
import re
# pyrefly: ignore [missing-import]
# pyrefly: ignore [import-error]
from fastapi import FastAPI, File, UploadFile, HTTPException
# pyrefly: ignore [missing-import]
# pyrefly: ignore [import-error]
from fastapi.responses import JSONResponse
# pyrefly: ignore [missing-import]
# pyrefly: ignore [import-error]
from fastapi.middleware.cors import CORSMiddleware
# pyrefly: ignore [missing-import]
from paddleocr import PaddleOCR
# pyrefly: ignore [missing-import]
import numpy as np
# pyrefly: ignore [missing-import]
import cv2

app = FastAPI(title="Oriotel OCR Service", version="1.0")

# Allow requests from any origin for local development.
# In production, restrict allowed_origins to your actual domain.
app.add_middleware(
    CORSMiddleware,
    allow_origin_regex=".*",
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize PaddleOCR model (downloads the lightweight model on first run)
# use_angle_cls=True automatically rotates the image if it was taken upside down
# lang='en' handles Latin letters (A-Z) and numbers (0-9) perfectly for the CIN
ocr = PaddleOCR(use_angle_cls=True, lang='en')

@app.get("/health")
def health_check():
    return {"status": "ok", "service": "ocr-python"}

@app.post("/api/v1/scan-cin")
async def scan_cin(file: UploadFile = File(...)):
    # Validate file type
    # Accept images only. PDF requires pdf2image conversion (not yet implemented).
    if file.content_type == "application/pdf":
        raise HTTPException(status_code=400, detail="PDF direct OCR not yet supported. Please upload a JPEG or PNG image of the CIN.")
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Unsupported file type. Please upload a JPEG or PNG image.")

    try:
        # Read image into memory (without saving to disk for maximum security)
        contents = await file.read()
        
        # Check max file size (5MB)
        if len(contents) > 5 * 1024 * 1024:
            return JSONResponse(status_code=400, content={
                "success": False,
                "error": "La taille de l'image ne doit pas dépasser 5MB."
            })

        nparr = np.frombuffer(contents, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Impossible de décoder l'image.")

        # Run PaddleOCR (No resolution limit as requested)
        # result contains bounding boxes and text
        result = ocr.ocr(img)

        extracted_text = []
        found_cin = None
        date_naissance = None
        adresse = None
        nom = None
        prenom = None

        if result and result[0]:
            lines = []
            boxes = []
            
            if isinstance(result[0], list):
                for line in result[0]:
                    if len(line) > 1 and len(line[1]) > 0:
                        text = line[1][0]
                        box = line[0]
                        lines.append(text)
                        boxes.append(box)
            elif isinstance(result[0], dict) and 'rec_texts' in result[0]:
                for text in result[0]['rec_texts']:
                    if text:
                        lines.append(text)
                        boxes.append(None)

            extracted_text = lines

            for i, text in enumerate(lines):
                clean_text = text.upper().replace(' ', '')
                
                # Extract CIN
                match_cin = re.search(r'\b[A-Z]{1,2}\d{4,6}\b', clean_text)
                if match_cin and not found_cin:
                    found_cin = match_cin.group(0)

                # Extract Date de naissance
                # Usually matches DD.MM.YYYY
                match_date = re.search(r'\b(\d{2}\.\d{2}\.\d{4})\b', text)
                if match_date:
                    date_val = match_date.group(1)
                    # We might find multiple dates (birth, validity). Birth year is usually < 2015.
                    year = int(date_val.split('.')[-1])
                    if year < 2020:
                        if not date_naissance:
                            date_naissance = date_val.replace('.', '-')
                            parts = date_val.split('.')
                            if len(parts) == 3:
                                date_naissance = f"{parts[2]}-{parts[1]}-{parts[0]}"

                # Extract Adresse
                if "ADRESSE" in text.upper():
                    addr_part = re.sub(r'(?i)ADRESSE\s*', '', text).strip()
                    if addr_part:
                        adresse = addr_part
                    elif i + 1 < len(lines):
                        adresse = lines[i+1].strip()

            # Name Candidates Heuristic
            candidates = []
            stop_words = ["ROYAUME", "MAROC", "CARTE", "NATIONALE", "IDENTITE", "VALABLE", "JUSQU", "CAMSCANNER", "NELE", "MZDAD", "MAROCAINE"]
            
            dob_index = -1
            for i, text in enumerate(extracted_text):
                text_up = text.upper()
                if "NÉ LE" in text_up or "NE LE" in text_up or "NELE" in text_up or "MZDAD" in text_up or (date_naissance and date_naissance[-4:] in text):
                    dob_index = i
                    break

            for i, text in enumerate(extracted_text):
                line_str = text.strip()
                if not line_str or len(line_str) < 3:
                    continue
                
                # Must not contain lowercase letters or numbers
                if re.search(r'[a-z\d]', line_str):
                    continue
                
                line_upper = line_str.upper()
                if any(sw in line_upper for sw in stop_words):
                    continue
                    
                # Must be mostly letters, max 2 words (names)
                clean_name = re.sub(r'[^A-Z\s-]', '', line_upper).strip()
                if len(clean_name) >= 3 and len(clean_name.split()) <= 2:
                    candidates.append({
                        "text": clean_name,
                        "index": i,
                        "dist": abs(i - dob_index) if dob_index != -1 else i
                    })
            
            # Sort candidates by distance to 'Né le'. The closest two are Nom and Prenom.
            candidates.sort(key=lambda c: c["dist"])
            
            if len(candidates) >= 2:
                # We have the two closest. Now we need to figure out which is Nom and which is Prenom.
                # In the array, they are usually adjacent. 
                c1, c2 = candidates[0], candidates[1]
                # To distinguish, if they are sorted from top-to-bottom (horizontal), Prenom is before Nom.
                # In horizontal, array is usually: [..., Prenom, Nom, Ne le, ...] -> indices: Prenom < Nom.
                # In vertical, array might be: [..., Ne le, Nom, Prenom, ...] -> indices: Nom < Prenom.
                # Actually, Prénom is always printed ABOVE Nom on the card.
                # Let's use the Y coordinate to be absolutely sure.
                y1 = sum([pt[1] for pt in boxes[c1["index"]]]) / 4.0 if boxes[c1["index"]] else c1["index"]
                y2 = sum([pt[1] for pt in boxes[c2["index"]]]) / 4.0 if boxes[c2["index"]] else c2["index"]
                
                # If Y difference is small, use X.
                if abs(y1 - y2) < 20:
                    x1 = sum([pt[0] for pt in boxes[c1["index"]]]) / 4.0 if boxes[c1["index"]] else c1["index"]
                    x2 = sum([pt[0] for pt in boxes[c2["index"]]]) / 4.0 if boxes[c2["index"]] else c2["index"]
                    if x1 < x2:
                        prenom, nom = c1["text"], c2["text"]
                    else:
                        prenom, nom = c2["text"], c1["text"]
                elif y1 < y2:
                    prenom, nom = c1["text"], c2["text"]
                else:
                    prenom, nom = c2["text"], c1["text"]
            elif len(candidates) == 1:
                nom = candidates[0]["text"]

        print("OCR Extracted Lines (Sorted):", extracted_text)
        print(f"Extracted -> CIN: {found_cin}, Nom: {nom}, Prenom: {prenom}, DOB: {date_naissance}, Addr: {adresse}", flush=True)

        return JSONResponse(content={
            "success": True,
            "cin": found_cin,
            "nom": nom,
            "prenom": prenom,
            "date_naissance": date_naissance,
            "adresse": adresse,
            "raw_text": extracted_text
        })

    except Exception as e:
        import traceback
        traceback.print_exc()
        return JSONResponse(status_code=500, content={
            "success": False,
            "error": str(e)
        })
