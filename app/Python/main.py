# app/Python/main.py

from fastapi import FastAPI, File, UploadFile
from fastapi.responses import StreamingResponse
from .daltonize_module import daltonize_image
import io

app = FastAPI()

@app.post("/daltonize")
async def daltonize_endpoint(file: UploadFile = File(...)):
    # アップロードされたファイルを読み込み
    contents = await file.read()

    # 画像を補正
    processed_image_bytes = daltonize_image(contents)

    # バイトデータをストリームとして返す
    return StreamingResponse(io.BytesIO(processed_image_bytes), media_type="image/png")
