# app/Python/main.py

from fastapi import FastAPI, File, UploadFile, Form 
from fastapi.responses import StreamingResponse
from .daltonize_module import daltonize_image
from .weakhosei import correct_image 
import io

app = FastAPI()

@app.post("/daltonize")
async def daltonize_endpoint(
    file: UploadFile = File(...),
    deficiency_type: str = Form(default='d')  # フォームからdeficiency_typeを受け取る
):
    # アップロードされたファイルを読み込み
    contents = await file.read()

    # 画像を補正（色覚タイプを指定）
    processed_image_bytes = daltonize_image(contents, color_deficit=deficiency_type)

    # バイトデータをストリームとして返す
    return StreamingResponse(io.BytesIO(processed_image_bytes), media_type="image/png")
# 新しいエンドポイントを追加


@app.post("/weakhosei")
async def weakhosei_endpoint(
    deficiency_type: str = Form(...),
    numerical_value: int = Form(...),
    file: UploadFile = File(...)
):
    # アップロードされたファイルを読み込み
    contents = await file.read()

    # 一時的なファイルに保存
    input_image_path = "temp_image.png"
    with open(input_image_path, "wb") as f:
        f.write(contents)

    # 画像補正関数を呼び出し
    output_image_path = correct_image(deficiency_type, numerical_value, input_image_path)

    # 補正後の画像を読み込み
    with open(output_image_path, "rb") as f:
        corrected_image_bytes = f.read()

    # 一時ファイルを削除（必要に応じて）
    # os.remove(input_image_path)
    # os.remove(output_image_path)

    # 補正後の画像を返す
    return StreamingResponse(io.BytesIO(corrected_image_bytes), media_type="image/png")
