from typing import Union
from fastapi import FastAPI, WebSocket, HTTPException
from typing import List
from pydantic import BaseModel
import importlib
import json
import app.Python.calil4
from app.Python.calil4 import main as calil_main
from app.Python.Ipygeo import get_device_location, verify_2fa_code
from app.Python.book_camera_code import get_isbn_from_camera
from app.Python.camera import get_book_info_and_save
from app.Python.recommend import run_recommendation

from fastapi.middleware.cors import CORSMiddleware

importlib.reload(app.Python.calil4)

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def read_root():
    return {"Hello": "World"}


@app.get("/items/{item_id}")
def read_item(item_id: int, q: Union[str, None] = None):
    return {"item_id": item_id, "q": q}

class VerificationCode(BaseModel):
    verification_code: str

class CalilRequest(BaseModel):
    isbn_list: List[str]
    
@app.post("/run-calil")
def run_calil(request: CalilRequest):
    latitude, longitude, error_message = get_device_location()
    
    if error_message == "2FA_REQUIRED":
        return {"requires_2fa": True}
    
    if error_message:
        return {"error": error_message}
    
    result = calil_main(request.isbn_list, latitude, longitude)
    return result

@app.post("/verify-2fa")
def verify_2fa(code: VerificationCode):
    success, error_message = verify_2fa_code(code.verification_code)
    if success:
        return {"message": "2段階認証が成功しました"}
    else:
        raise HTTPException(status_code=400, detail=error_message)
    
class ISBNList(BaseModel):
    isbn_list: List[str]

@app.get("/get-isbn")
def run_get_isbn():
    try:
        isbn = get_isbn_from_camera()
        if isbn:
            return {"isbn": isbn}
        else:
            return {"error": "ISBNの取得に失敗しました"}, 500
    except Exception as e:
        return {"error": str(e)}, 500

@app.post("/get-book-info")
def run_get_book_info(isbn_data: ISBNList):
    return get_book_info_and_save(isbn_data.isbn_list)

@app.post("/run-recommend")
def run_recommend(request: dict):
    try:
        genre_id = request.get('genre_id')
        isbn_list = request.get('isbn_list')
        
        if not genre_id or not isbn_list:
            raise ValueError("genre_id and isbn_list are required")
        
        recommended_isbns = run_recommendation(genre_id, isbn_list)
        return recommended_isbns
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    data = await websocket.receive_json()
    genre_id = data.get('genre_id')
    isbn_list = data.get('isbn_list')
    
    async def send_progress(progress: int, total: int):
        await websocket.send_json({"progress": progress, "total": total})

    try:
        recommended_isbns = await run_recommendation(genre_id, isbn_list, send_progress)
        await websocket.send_json({"recommended_isbns": recommended_isbns})
    except Exception as e:
        await websocket.send_json({"error": str(e)})
    finally:
        await websocket.close()

@app.get("/get-all-isbns")
def get_all_isbns():
    try:
        with open('/code/app/json/book_info.json', 'r', encoding='utf-8') as f:
            book_data = json.load(f)
        
        isbn_list = [book['ISBNコード'] for book in book_data if 'ISBNコード' in book]
        return {"isbn_list": isbn_list}
    except FileNotFoundError:
        raise HTTPException(status_code=404, detail="Book info file not found")
    except json.JSONDecodeError:
        raise HTTPException(status_code=500, detail="Error decoding JSON")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))