import requests
import json
import os  


# 楽天ブックスAPIのエンドポイントとアプリケーションID
API_ENDPOINT = "https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404"
APP_ID = "1083074937620035376"  # ここに実際のアプリケーションIDを入力してください

def get_book_info(isbn):
    params = {
        "format": "json",
        "applicationId": APP_ID,
        "isbn": isbn
    }

    response = requests.get(API_ENDPOINT, params=params)
    
    if response.status_code == 200:
        data = response.json()
        if "Items" in data and data["Items"]:
            book = data["Items"][0]["Item"]
            return {
                "タイトル": book.get("title", "タイトル情報がありません"),
                "著者名": book.get("author", "著者情報がありません"),
                "出版社名": book.get("publisherName", "出版社情報がありません"),
                "発売日": book.get("salesDate", "発売日の情報がありません"),
                "レビュー件数": book.get("reviewCount", "レビュー件数の情報がありません"),
                "レビュー平均": book.get("reviewAverage", "レビュー平均の情報がありません"),
                "価格（税込）": book.get("itemPrice", "価格情報がありません"),
                "ISBNコード": isbn,
                "商品URL": book.get("itemUrl", "商品のURLがありません"),
                "商品画像URL": book.get("mediumImageUrl", "商品の画像URLがありません"),
                "あらすじ": book.get("itemCaption", "あらすじが提供されていません。")
            }
    return None

def print_book_info(book_info):
    if book_info:
        print(json.dumps(book_info, ensure_ascii=False, indent=4))  # JSON形式で整形して出力
    else:
        print("本の情報を取得できませんでした。ISBNを確認してください。")
    print("-" * 50)

def save_book_info_to_json(book_info_list):
    # "books" キーの下にデータを保存する形式に変更
    data = {"books": book_info_list}
    
    # 保存先フォルダを定義（コンテナ内のパス）
    folder = '/code/app/json'
    
    # フォルダが存在しなければ作成
    if not os.path.exists(folder):
        os.makedirs(folder)
    
    # ファイルパスを指定
    file_path = os.path.join(folder, "book_info.json")
    
    # ファイルに書き込み
    with open(file_path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
    
    print(f"データが {file_path} に保存されました。")
    
    # Laravelプロジェクト内での相対パスも表示（デバッグ用）
    laravel_path = 'storage/app/json/book_info.json'
    print(f"Laravelプロジェクト内のパス: {laravel_path}")

    return file_path

def get_book_info_and_save(isbn_list):
    book_info_list = []
    for isbn in isbn_list:
        print(f"Getting info for ISBN: {isbn}")  # デバッグ用
        book_info = get_book_info(isbn)
        if book_info:
            book_info_list.append(book_info)
        else:
            print(f"No info found for ISBN: {isbn}")  # デバッグ用
    if book_info_list:
        print(f"Saving info for {len(book_info_list)} books")  # デバッグ用
        save_book_info_to_json(book_info_list)
    else:
        print("No book info to save")  # デバッグ用
    return book_info_list

# この部分はエンドポイントでは使用されませんが、
# スクリプトを直接実行した場合のテスト用に残しておきます
if __name__ == '__main__':
    isbn_list = []
    for i in range(3):
        isbn = input(f"{i+1}冊目のISBNを入力してください(13桁):")
        isbn_list.append(isbn)
    print(get_book_info_and_save(isbn_list))