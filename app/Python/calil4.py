import requests
import time
import json
from app.Python.Ipygeo import get_device_location

# あなたのアプリケーションキーを設定してください
APP_KEY = ''

# 検索するISBN
ISBN = '97840621945'

# 指定の緯度経度
LATITUDE = 35.9852825
LONGITUDE = 139.3710556

def get_nearby_libraries(latitude, longitude, limit=20):
    """
    指定された緯度経度に基づいて近隣の図書館を取得します。
    """
    url = 'https://api.calil.jp/library'
    params = {
        'appkey': APP_KEY,
        'geocode': f'{longitude},{latitude}',
        'limit': limit,
        'format': 'json',
        'callback': ''
    }
    response = requests.get(url, params=params)
    libraries = response.json()

    print("近くの図書館のレスポンス:", libraries)
    
    return libraries

def check_book_availability(isbn, systemids):
    """
    指定されたISBNとシステムIDに基づいて本の利用状況を確認します。
    """
    url = 'https://api.calil.jp/check'
    params = {
        'appkey': APP_KEY,
        'isbn': isbn,
        'systemid': ','.join(systemids),
        'format': 'json',
        'callback': 'no'
    }
    response = requests.get(url, params=params)
    data = response.json()
    session = data['session']
    continue_flag = data['continue']

    # ポーリングしてすべての結果を取得
    while continue_flag == 1:
        time.sleep(2)  # 2秒以上間隔をあける
        params = {
            'appkey': APP_KEY,
            'session': session,
            'format': 'json',
            'callback': 'no'
        }
        response = requests.get(url, params=params)
        data = response.json()
        continue_flag = data['continue']
    return data

def is_status_valid(lib_info):
    """
    図書館のステータスが有効かどうかをチェックします。
    """
    status = lib_info.get('status')
    return status in ['OK', 'Cache']

def is_lend_status_valid(lend_status):
    """
    貸出ステータスが有効かどうかをチェックします。
    """
    return lend_status in ['貸出可', '蔵書あり', '館内のみ']

def find_library(systemid, libkey, libraries):
    """
    systemidとlibkeyに一致する図書館を検索します。
    """
    for lib in libraries:
        if lib.get('systemid') == systemid and lib.get('libkey') == libkey:
            return lib
    return None

def get_available_libraries(books, libraries):
    """
    蔵書情報から利用可能な図書館を抽出します。
    """
    available_libraries = []
    for isbn_key, systems in books.items():
        for systemid, lib_info in systems.items():
            if not is_status_valid(lib_info):
                continue
            libkeys = lib_info.get('libkey', {})
            for libkey, lend_status in libkeys.items():
                if not is_lend_status_valid(lend_status):
                    continue
                library = find_library(systemid, libkey, libraries)
                if library:
                    available_libraries.append(library)
    return available_libraries

# def main(latitude=None, longitude=None):
    # try:
        # lpygeo.pyから緯度経度を取得
        # latitude, longitude, error_code = get_device_location()
        # latitude = 35.9852825
        # longitude = 139.3710556
def main(isbn_list, latitude=None, longitude=None):
    try:
        if latitude is None or longitude is None:
            latitude, longitude, error_code = get_device_location()
            
            if error_code:
                error_messages = {
                    "ENV_ERROR": "iCloudアカウント情報が環境変数に設定されていません。",
                    "2FA_REQUIRED": "2段階認証が必要です。",
                    "NO_LOCATION": "MacBookの位置情報が取得できませんでした。",
                    "LOGIN_FAILED": "iCloudログインに失敗しました。アカウント情報を確認してください。",
                    "SERVICE_UNAVAILABLE": "iCloudサービスにアクセスできません。",
                }
                return {"error": error_messages.get(error_code, f"エラーが発生しました: {error_code}")}

        if not latitude or not longitude:
            return {"error": "緯度経度を取得できませんでした。"}

        libraries = get_nearby_libraries(latitude, longitude)
        if not libraries:
            return {"error": '近くに図書館が見つかりませんでした。'}

        systemids = list(set([lib.get('systemid') for lib in libraries if 'systemid' in lib]))

        results = []
        for isbn in isbn_list:
            availability_data = check_book_availability(isbn, systemids)
            books = availability_data.get('books', {})
            available_libraries = get_available_libraries(books, libraries)

            if not available_libraries:
                results.append({
                    'ISBN': isbn,
                    'error': '指定された本を借りられる図書館が見つかりませんでした。'
                })
                continue

            closest_library = min(
                available_libraries,
                key=lambda x: float(x.get('distance', float('inf')))
            )
            
            results.append({
                'ISBN': isbn,
                'name': closest_library.get('formal'),
                'address': closest_library.get('address'),
                'distance': f"{closest_library.get('distance')} km",
                'status': '貸出可',
                'library_url': f'https://calil.jp/library/{closest_library.get("libid")}/{closest_library.get("formal")}',
            })
        
        return results

    except Exception as e:
        error_result = {'error': str(e)}
        print(json.dumps(error_result, ensure_ascii=False, indent=4))
        return error_result

if __name__ == '__main__':
    main()
