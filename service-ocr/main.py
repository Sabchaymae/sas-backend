import os
import re
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from paddleocr import PaddleOCR
import numpy as np
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
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier doit être une image.")

    try:
        # Read image into memory (without saving to disk for maximum security)
        contents = await file.read()
        nparr = np.frombuffer(contents, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Impossible de décoder l'image.")

        # Run PaddleOCR
        # result contains bounding boxes and text
        result = ocr.ocr(img, cls=True)

        extracted_text = []
        found_cin = None

        if result and result[0]:
            for line in result[0]:
                text = line[1][0]
                extracted_text.append(text)
                
                # Regex for Moroccan CIN: 1 or 2 uppercase letters followed by 4 to 6 digits
                # We remove spaces in case the OCR reads "A B 12345"
                clean_text = text.upper().replace(' ', '')
                match = re.search(r'\b[A-Z]{1,2}\d{4,6}\b', clean_text)
                
                if match and not found_cin:
                    found_cin = match.group(0)

        return JSONResponse(content={
            "success": True,
            "cin": found_cin,
            "raw_text": extracted_text
        })

    except Exception as e:
        return JSONResponse(status_code=500, content={
            "success": False,
            "error": str(e)
        })
