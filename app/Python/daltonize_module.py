# app/Python/daltonize_module.py

import numpy as np
from PIL import Image, ImageDraw, ImageFont
import io
import os
from ultralytics import YOLO

# モデルファイルのパスを指定
#MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models', 'best.pt')#yakiniku
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models', 'yolov8n.pt')
#MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models', 'banana_e50.pt')
# YOLO モデルのロード（初期化時に一度だけ行う）
try:
    model = YOLO(MODEL_PATH)
    print(f"モデルが正常にロードされました: {MODEL_PATH}")
except Exception as e:
    print(f"モデルのロード中にエラーが発生しました: {e}")
    raise e  # 重大なエラーのため、再スローします

def transform_colorspace(img, mat):
    return img @ mat.T

def simulate(rgb, color_deficit="d"):
    cb_matrices = {
        "d": np.array([[1, 0, 0], [1.10104433,  0, -0.00901975], [0, 0, 1]], dtype=np.float16),
        "p": np.array([[0, 0.90822864, 0.008192], [0, 1, 0], [0, 0, 1]], dtype=np.float16),
        "t": np.array([[1, 0, 0], [0, 1, 0], [-0.15773032,  1.19465634, 0]], dtype=np.float16),
    }
    rgb2lms = np.array([[0.3904725 , 0.54990437, 0.00890159],
           [0.07092586, 0.96310739, 0.00135809],
           [0.02314268, 0.12801221, 0.93605194]], dtype=np.float16)
    lms2rgb = np.array([[ 2.85831110e+00, -1.62870796e+00, -2.48186967e-02],
           [-2.10434776e-01,  1.15841493e+00,  3.20463334e-04],
           [-4.18895045e-02, -1.18154333e-01,  1.06888657e+00]], dtype=np.float16)
    lms = transform_colorspace(rgb, rgb2lms)
    sim_lms = transform_colorspace(lms, cb_matrices[color_deficit])
    sim_rgb = transform_colorspace(sim_lms, lms2rgb)
    return np.clip(sim_rgb, 0, 1)

def daltonize(rgb, color_deficit='d'):
    sim_rgb = simulate(rgb, color_deficit)
    err2mod = np.array([[0, 0, 0], [0.7, 1, 0], [0.7, 0, 1]])
    err = transform_colorspace(rgb - sim_rgb, err2mod)
    dtpn = err + rgb
    return np.clip(dtpn, 0, 1)

def gamma_correction(rgb, gamma=2.4):
    linear_rgb = np.zeros_like(rgb, dtype=np.float16)
    for i in range(3):
        idx = rgb[:, :, i] > 0.04045 * 255
        linear_rgb[idx, i] = ((rgb[idx, i] / 255 + 0.055) / 1.055)**gamma
        idx = np.logical_not(idx)
        linear_rgb[idx, i] = rgb[idx, i] / 255 / 12.92
    return linear_rgb

def inverse_gamma_correction(linear_rgb, gamma=2.4):
    rgb = np.zeros_like(linear_rgb, dtype=np.float16)
    for i in range(3):
        idx = linear_rgb[:, :, i] <= 0.0031308
        rgb[idx, i] = 255 * 12.92 * linear_rgb[idx, i]
        idx = np.logical_not(idx)
        rgb[idx, i] = 255 * (1.055 * linear_rgb[idx, i]**(1/gamma) - 0.055)
    return np.round(rgb)

def array_to_img(arr, gamma=2.4):
    arr = inverse_gamma_correction(arr, gamma=gamma)
    arr = np.clip(arr, 0, 255)
    arr = arr.astype('uint8')
    img = Image.fromarray(arr, mode='RGB')
    return img

def daltonize_image(image_bytes, color_deficit='d'):
    try:
        # バイトデータからPILイメージを作成
        image = Image.open(io.BytesIO(image_bytes)).convert('RGB')
        print(f"オリジナル画像サイズ: {image.size}")

        # 画像を numpy 配列に変換（オリジナル画像用、dtype=uint8）
        original_img_array = np.array(image, dtype=np.uint8)

        # オリジナル画像を物体検出のソースとして使用
        results = model.predict(
            source=original_img_array,
            conf=0.55,
            imgsz=640,
            verbose=False
        )

        if not results or len(results) == 0:
            print("物体検出結果がありません")
        else:
            print(f"検出結果の数: {len(results)}")

        # 画像を numpy 配列に変換（ダルトナイズ用、dtype=float16）
        img_array = original_img_array.astype(np.float16)
        linear_rgb = gamma_correction(img_array)
        daltonized_rgb = daltonize(linear_rgb, color_deficit=color_deficit)
        processed_image = array_to_img(daltonized_rgb)

        # フォントの設定
        try:
            font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
            font = ImageFont.truetype(font_path, size=20)
        except IOError:
            print("指定したフォントが見つかりません。デフォルトフォントを使用します。")
            font = ImageFont.load_default()

        # バウンディングボックスを描画
        draw = ImageDraw.Draw(processed_image)

        if results and len(results) > 0:
            result = results[0]
            boxes = result.boxes

            for box, cls, conf in zip(boxes.xyxy, boxes.cls, boxes.conf):
                x1, y1, x2, y2 = map(int, box)
                cls_id = int(cls)
                conf_value = float(conf)

                # クラス名の取得
                cls_name = model.names[cls_id]

                # バウンディングボックスの描画（太さ3の赤色）
                draw.rectangle([x1, y1, x2, y2], outline=(255, 0, 0), width=3)

                # ラベルテキストの作成
                label = f"{cls_name} {conf_value:.2f}"

                # テキストサイズの取得（変更箇所）
                bbox = font.getbbox(label)
                text_width = bbox[2] - bbox[0]
                text_height = bbox[3] - bbox[1]

                # ラベル背景の描画（半透明の白色）
                margin = 4
                draw.rectangle(
                    [x1, y1 - text_height - margin * 2, x1 + text_width + margin * 2, y1],
                    fill=(255, 255, 255, 220)
                )

                # ラベルテキストの描画
                draw.text(
                    (x1 + margin, y1 - text_height - margin),
                    label,
                    fill=(255, 0, 0),
                    font=font
                )

                print(f"検出: {cls_name} ({conf_value:.2f}) at ({x1}, {y1}, {x2}, {y2})")

        # イメージをバイトデータに変換
        output = io.BytesIO()
        processed_image.save(output, format='PNG')
        return output.getvalue()

    except Exception as e:
        print(f"エラーが発生しました: {e}")
        raise e
