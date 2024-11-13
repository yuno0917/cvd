import requests
import pandas as pd
import time
import json  # JSON保存のために追加
import os  # フォルダ操作のために追加

# あなたのアプリケーションIDを設定
APPLICATION_ID = '1017568727024257356'  # ここに正しいアプリケーションIDを入力

# BooksBook/Search APIエンドポイント
API_URL = 'https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404'

# リクエスト回数をカウントするグローバル変数
request_count = 0

def fetch_books(application_id, genre_id, sort, hits=30, page=1):
    """
    指定されたジャンルIDで書籍を検索し、指定されたソート順でデータを取得します。
    
    Args:
        application_id (str): アプリケーションID
        genre_id (str): 楽天ブックスジャンルID
        sort (str): ソート順（例: reviewCount）
        hits (int): 1ページあたりの取得件数（最大30）
        page (int): 取得するページ番号
    
    Returns:
        list: 書籍データのリスト
    """
    global request_count
    params = {
        'applicationId': application_id,
        'booksGenreId': genre_id,
        'format': 'json',
        'sort': sort,
        'hits': hits,
        'page': page
    }
    
    try:
        response = requests.get(API_URL, params=params, timeout=10)
        request_count += 1  # リクエスト回数をカウント
        response.raise_for_status()
        data = response.json()
        if 'Items' in data:
            return data['Items']
        else:
            print('Itemsフィールドが見つかりませんでした。レスポンスを確認してください。')
            return []
    except requests.exceptions.RequestException as e:
        print(f'APIリクエストエラー: {e}')
        return []
    except ValueError:
        print('JSONの解析に失敗しました。')
        return []

def fetch_books_with_retry(application_id, genre_id, sort, hits=30, page=1, retries=3, backoff_factor=1):
    """
    APIリクエストに失敗した場合にリトライを試みます。
    
    Args:
        application_id (str): アプリケーションID
        genre_id (str): 楽天ブックスジャンルID
        sort (str): ソート順
        hits (int): 1ページあたりの取得件数
        page (int): 取得するページ番号
        retries (int): リトライ回数
        backoff_factor (int): リトライ間隔の基数
    
    Returns:
        list: 書籍データのリスト
    """
    for attempt in range(retries):
        books = fetch_books(application_id, genre_id, sort, hits, page)
        if books:
            return books
        else:
            wait_time = backoff_factor * (2 ** attempt)
            print(f'リトライ {attempt + 1}/{retries} を {wait_time} 秒後に実行します...')
            time.sleep(wait_time)
    print(f'ページ {page} のデータ取得に失敗しました。')
    return []

def extract_book_info(book):
    """
    書籍データから必要な情報を抽出します。
    
    Args:
        book (dict): 書籍データの辞書
    
    Returns:
        dict: 抽出した書籍情報
    """
    item = book.get('Item', {})
    return {
        'タイトル': item.get('title'),
        '著者名': item.get('author'),
        '出版社名': item.get('publisherName'),
        '発売日': item.get('salesDate'),
        'レビュー件数': item.get('reviewCount'),
        'レビュー平均': item.get('reviewAverage'),
        '価格（税込）': item.get('itemPrice'),
        'ISBNコード': item.get('isbn'),
        '商品URL': item.get('itemUrl'),
        '商品画像URL': item.get('mediumImageUrl'),
        'あらすじ': item.get('itemCaption')  # 追加
    }

import os

def save_to_json(books_info, genre_id, filename=None):
    # プロジェクトのルートディレクトリ（book_recommend）を取得
    project_root = find_project_root()
    
    # 保存先フォルダを定義
    folder = os.path.join(project_root, 'storage', 'app', 'json')
    
    # フォルダが存在しなければ作成
    if not os.path.exists(folder):
        os.makedirs(folder)
    
    # ジャンルIDを含むファイル名を生成
    if filename is None:
        filename = f'{genre_id}_books_500.json'
    
    # ファイルパスを指定
    file_path = os.path.join(folder, filename)
    
    try:
        with open(file_path, 'w', encoding='utf-8') as f:
            json.dump(books_info, f, ensure_ascii=False, indent=4)
        print(f'データが {file_path} に保存されました。')
        
        # Laravelプロジェクト内での相対パスも表示（デバッグ用）
        laravel_path = os.path.join('storage', 'app', 'json', filename)
        print(f"Laravelプロジェクト内のパス: {laravel_path}")
    except IOError as e:
        print(f'ファイル保存エラー: {e}')

    return file_path

def find_project_root():
    """プロジェクトのルートディレクトリ（book_recommend）を見つける"""
    current_dir = os.path.dirname(os.path.abspath(__file__))
    while True:
        if os.path.basename(current_dir) == 'book_recommend':
            return current_dir
        parent_dir = os.path.dirname(current_dir)
        if parent_dir == current_dir:  # ルートディレクトリに到達
            raise Exception("book_recommendディレクトリが見つかりません")
        current_dir = parent_dir
        
def main():
    global request_count
    print(f'Using APPLICATION_ID: {APPLICATION_ID}')  # デバッグ用出力

    # 確認したいジャンルID
    genre_id = '001017'
    # ノンフィクション (ID: 001004004)
    # エッセイ (ID: 001004003)
    # SF・ホラー (ID: 001004002)
    # ライトノベル (ID: 001017)
    # ロマンス (ID: 001004016)
    # トップレベルジャンル (ID: 001)全体？

    # ソート順の設定
    sort_order = 'reviewCount'  # レビュー件数の多い順

    total_desired = 500  # 取得したい総書籍数
    hits_per_page = 30  # 1ページあたりの取得件数
    pages_needed = (total_desired // hits_per_page) + (1 if total_desired % hits_per_page != 0 else 0)

    all_books = []

    print('書籍データを取得中...')
    for page in range(1, pages_needed + 1):
        print(f'ページ {page} を取得中...')
        books = fetch_books_with_retry(APPLICATION_ID, genre_id, sort_order, hits=hits_per_page, page=page)
        if not books:
            print(f'ページ {page} のデータが取得できませんでした。終了します。')
            break
        all_books.extend(books)
        # APIレート制限に配慮して、適宜待機時間を設ける
        time.sleep(1)  # 1秒の待機

    # 必要な数だけ切り取る
    all_books = all_books[:total_desired]

    if not all_books:
        print('書籍データが取得できませんでした。')
        return

    print('書籍データを解析中...')
    books_info = [extract_book_info(book) for book in all_books]

    print('JSONファイルに保存中...')
    save_to_json(books_info, genre_id)

    print(f"現在の作業ディレクトリ: {os.getcwd()}")
    print(f"プロジェクトルート: {find_project_root()}")
    # リクエスト回数を表示
    print(f'\nAPIリクエスト回数: {request_count} 回')
    print(f'合計取得書籍数: {len(books_info)} 冊')

if __name__ == '__main__':
    main()
