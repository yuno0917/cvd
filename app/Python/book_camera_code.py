import cv2

from pyzbar.pyzbar import decode
from pyzbar.pyzbar import ZBarSymbol
import re
import numpy as np
import math
#####################################################
# 機能　ISBNバーコードを自動撮影し、コードを返す
# 入口　convert_image_to_code()
# 出力　ISBN番号
#####################################################
# 注意　複数カメラを接続している場合、22行を修正して使用
##################################################### 
def contrast(image, a):
    lut = [ np.uint8(255.0 / (1 + math.exp(-a * (i - 128.) / 255.))) for i in range(256)]
    result_image = np.array( [ lut[value] for value in image.flat], dtype=np.uint8 )
    result_image = result_image.reshape(image.shape)
    return result_image

def convert_image_to_code():
    capture = cv2.VideoCapture(0) # カメラ番号を選択、例えば　capture = cv2.VideoCapture(1)
    if capture.isOpened() is False:
        raise("IO Error")
    cv2.namedWindow("Capture", cv2.WINDOW_AUTOSIZE)
    isbnNumber = ""
    while True:
        ret, image = capture.read()
        image_mirror = image[:,::-1]
        if ret == False:
            continue
        # GrayScale
        imageGlay = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        image = contrast(imageGlay, 5)

        # Show image
        cv2.imshow("Capture", image_mirror )

        allCodes = decode(image, symbols=[ZBarSymbol.EAN13])

        if len(allCodes) > 0: # Barcode was detected
            for code in allCodes:
                codesStr = str(code)
                isbnPattern = r"9784\d+"
                isbnSearchOB = re.search(isbnPattern,codesStr)
                if isbnSearchOB: # ISBN was detected
                    if isbnNumber != isbnSearchOB.group(): # New ISBN was detected
                        isbnNumber = isbnSearchOB.group()
                        capture.release()
                        cv2.destroyAllWindows()
                        return isbnNumber

        keyInput = cv2.waitKey(3) # 撮影速度 大きくなると遅くなる
        if keyInput == 27: # when ESC
            break

# if __name__ == '__main__':

#     code = convert_image_to_code()
#     print(code)
def get_isbn_from_camera():
    try:
        # MacOSの場合、0はデフォルトカメラを指します
        capture = cv2.VideoCapture(0)
        if not capture.isOpened():
            raise IOError("カメラを開けません")
        
        # ここに残りのコードを記述...
        
        # テスト用：ハードコードされたISBNを返す
        return "9784062194501"  # 例のISBN
    except Exception as e:
        print(f"カメラエラー: {str(e)}")
        return None


if __name__ == '__main__':
    print(get_isbn_from_camera())