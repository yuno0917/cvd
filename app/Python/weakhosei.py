from enum import Enum
import numpy as np
import cv2

# 色覚特性の種類を定義
class Deficiency(Enum):
    PROTAN = "protan"
    DEUTAN = "deutan"

# Machado et al. (2009)の色変換行列
machado_2009_matrices = {
    Deficiency.PROTAN: {
        0: np.array([[1.000000, 0.000000, -0.000000],
                     [0.000000, 1.000000, 0.000000],
                     [-0.000000, -0.000000, 1.000000]]),
        1: np.array([[0.856167, 0.182038, -0.038205],
                     [0.029342, 0.955115, 0.015544],
                     [-0.002880, -0.001563, 1.004443]]),
        2: np.array([[0.734766, 0.334872, -0.069637],
                     [0.051840, 0.919198, 0.028963],
                     [-0.004928, -0.004209, 1.009137]]),
        3: np.array([[0.630323, 0.465641, -0.095964],
                     [0.069181, 0.890046, 0.040773],
                     [-0.006308, -0.007724, 1.014032]]),
        4: np.array([[0.539009, 0.579343, -0.118352],
                     [0.082546, 0.866121, 0.051332],
                     [-0.007136, -0.011959, 1.019095]]),
        5: np.array([[0.458064, 0.679578, -0.137642],
                     [0.092785, 0.846313, 0.060902],
                     [-0.007494, -0.016807, 1.024301]]),
        6: np.array([[0.385450, 0.769005, -0.154455],
                     [0.100526, 0.829802, 0.069673],
                     [-0.007442, -0.022190, 1.029632]]),
    },
    Deficiency.DEUTAN: {
        0: np.array([[1.000000, 0.000000, -0.000000],
                     [0.000000, 1.000000, 0.000000],
                     [-0.000000, -0.000000, 1.000000]]),
        1: np.array([[0.866435, 0.177704, -0.044139],
                     [0.049567, 0.939063, 0.011370],
                     [-0.003453, 0.007233, 0.996220]]),
        2: np.array([[0.760729, 0.319078, -0.079807],
                     [0.090568, 0.889315, 0.020117],
                     [-0.006027, 0.013325, 0.992702]]),
        3: np.array([[0.675425, 0.433850, -0.109275],
                     [0.125303, 0.847755, 0.026942],
                     [-0.007950, 0.018572, 0.989378]]),
        4: np.array([[0.605511, 0.528560, -0.134071],
                     [0.155318, 0.812366, 0.032316],
                     [-0.009376, 0.023176, 0.986200]]),
        5: np.array([[0.547494, 0.607765, -0.155259],
                     [0.181692, 0.781742, 0.036566],
                     [-0.010410, 0.027275, 0.983136]]),
        6: np.array([[0.498864, 0.674741, -0.173604],
                     [0.205199, 0.754872, 0.039929],
                     [-0.011131, 0.030969, 0.980162]]),
    }
}

def get_matrix_index(deficiency_type, numerical_value):
    # 数値を0から10のインデックスにマッピング
    if deficiency_type == Deficiency.DEUTAN:
        if numerical_value >= 22:
            index = 6
        elif 18 <= numerical_value < 22:
            index = 6
        elif 13 <= numerical_value < 18:
            index = 5
        elif 10 <= numerical_value < 13:
            index = 4
        elif 7 <= numerical_value < 10:
            index = 3
        elif 4 <= numerical_value < 7:
            index = 2
        elif 1 <= numerical_value < 4:
            index = 1
        else:
            index = 0  # 数値が1未満の場合
    elif deficiency_type == Deficiency.PROTAN:
        if numerical_value >= 15:
            index = 6
        elif 13 <= numerical_value < 15:
            index = 6
        elif 10 <= numerical_value < 13:
            index = 5
        elif 6 <= numerical_value < 10:
            index = 4
        elif 4 <= numerical_value < 6:
            index = 3
        elif 2 <= numerical_value < 4:
            index = 2
        elif numerical_value == 1:
            index = 1
        else:
            index = 0  # 数値が1未満の場合
    else:
        index = 0  # デフォルトの変換行列
    return index

def correct_image(deficiency_type_input, numerical_value_input, input_image_path):
    # 色覚特性の設定
    if deficiency_type_input.lower() == 'protan':
        deficiency_enum = Deficiency.PROTAN
    elif deficiency_type_input.lower() == 'deutan':
        deficiency_enum = Deficiency.DEUTAN
    else:
        raise ValueError(f"未知の色覚特性: {deficiency_type_input}")

    numerical_value = numerical_value_input

    # 変換行列のインデックス取得
    index = get_matrix_index(deficiency_enum, numerical_value)

    # 変換行列の選択
    M = machado_2009_matrices[deficiency_enum][index]

    # 逆変換行列の計算
    M_inv = np.linalg.inv(M)

    # 画像の読み込み
    img = cv2.imread(input_image_path)
    if img is None:
        raise FileNotFoundError("画像を読み込めませんでした。ファイルパスとファイル名を確認してください。")

    # BGR→RGB変換と正規化
    img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    img_norm = img / 255.0

    # darkening_factorの計算
    positive_sums = np.maximum(M_inv, 0).sum(axis=1)
    max_positive_sum = positive_sums.max()
    darkening_factor = 1 / max_positive_sum

    # 暗くする処理
    img_darkened_norm = np.clip(img_norm * darkening_factor, 0, 1)

    # 画像を2次元配列に変換
    h, w, c = img_darkened_norm.shape
    img_flat = img_darkened_norm.reshape(-1, 3)

    # 逆変換行列の適用
    img_transformed_flat = np.clip(np.dot(img_flat, M_inv.T), 0, 1)
    img_transformed = img_transformed_flat.reshape(h, w, 3)

    # 変換後の画像を0-255の範囲に変換してBGR形式に戻す
    img_transformed_uint8 = cv2.cvtColor((img_transformed * 255).astype(np.uint8), cv2.COLOR_RGB2BGR)

    # 変換後の画像を保存
    output_image_path = f'corrected_{deficiency_type_input}_{numerical_value}.png'
    cv2.imwrite(output_image_path, img_transformed_uint8)
    return output_image_path
