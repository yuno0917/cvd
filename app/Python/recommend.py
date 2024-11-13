import pandas as pd
import numpy as np
from transformers import BertModel, AutoTokenizer
import torch
from transformers import BertJapaneseTokenizer
from sklearn.metrics.pairwise import cosine_similarity
from tqdm import tqdm
import json  # JSON操作のために追加
import os  # フォルダ操作用
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity

# 1. データの読み込み
def load_json_to_df(filename):
    """
    JSONファイルを読み込み、Pandas DataFrameに変換します。

    Args:
        filename (str): 読み込むJSONファイルのパス。

    Returns:
        pd.DataFrame: 読み込まれたデータフレーム。
    """
    try:
        with open(filename, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # "books" キーがある場合はそれを使い、なければそのままリスト形式で扱う
        if isinstance(data, dict) and 'books' in data:
            data = data['books']
        
        return pd.DataFrame(data)  # DataFrameに変換
    except FileNotFoundError as e:
        print(f"ファイルが見つかりません: {e}")
        exit(1)
    except json.JSONDecodeError as e:
        print(f"JSONの解析に失敗しました: {e}")
        exit(1)

# 4. エンベディング生成関数（バッチ処理導入）
def get_embeddings(texts, tokenizer, model, device, max_length=256, batch_size=8):
    """
    テキストのリストからBERTエンベディングを生成します。

    Args:
        texts (list): テキストのリスト。
        tokenizer: トークナイザーオブジェクト。
        model: BERTモデルオブジェクト。
        device: 使用するデバイス（CPUまたはGPU）。
        max_length (int): トークンの最大長。
        batch_size (int): バッチサイズ。

    Returns:
        np.ndarray: エンベディングの配列。
    """
    embeddings = []
    for i in tqdm(range(0, len(texts), batch_size), desc="Generating embeddings in batches"):
        batch_texts = texts[i:i+batch_size]
        inputs = tokenizer(batch_texts, return_tensors='pt', truncation=True, padding=True, max_length=max_length)
        inputs = {k: v.to(device) for k, v in inputs.items()}
        with torch.no_grad():
            outputs = model(**inputs)
        cls_embeddings = outputs.last_hidden_state[:, 0, :].cpu().numpy()
        embeddings.extend(cls_embeddings)
    return np.array(embeddings)

def save_df_to_json(df, filename, columns=None):
    """
    DataFrameをJSONファイルに保存します。

    Args:
        df (pd.DataFrame): 保存するデータフレーム。
        filename (str): 保存するJSONファイルのパス。
        columns (list, optional): 保存するカラムのリスト。デフォルトは全カラム。
    """
    try:
        if columns:
            df_to_save = df[columns]
        else:
            df_to_save = df
        df_to_save.to_json(filename, orient='records', force_ascii=False, indent=4)
        print(f"推薦結果が '{filename}' に保存されました。")
    except Exception as e:
        print(f"エクスポート中にエラーが発生しました: {e}")
        
def run_recommendation(genre_id, isbn_list):
    # ジャンルに基づいた500冊のデータを読み込む
    books_df = load_json_to_df(f'/code/app/json/{genre_id}_books_500.json')
    
    # ユーザーが選んだ3冊の本のデータを読み込む
    user_books_df = load_json_to_df('/code/app/json/book_info.json')
    # user_books_df = user_books_df[user_books_df['ISBNコード'].isin(isbn_list)]

    # 必要なカラムの確認
    required_columns = ['タイトル', '著者名', '出版社名', '発売日', 'レビュー件数',
                        'レビュー平均', '価格（税込）', 'ISBNコード', '商品URL',
                        '商品画像URL', 'あらすじ']

    assert all(col in books_df.columns for col in required_columns), "500冊のデータに必要なカラムが不足しています。"

    # 2. データのクリーニング
    books_df = books_df.dropna(subset=['あらすじ']).reset_index(drop=True)
    user_books_df = user_books_df.dropna(subset=['あらすじ']).reset_index(drop=True)

    # 3. BERTモデルの準備
    model_name = 'cl-tohoku/bert-base-japanese'
    tokenizer = AutoTokenizer.from_pretrained(model_name)  # AutoTokenizerを使用
    model = BertModel.from_pretrained(model_name)
    device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

    # モデルをGPUに移動する前にキャッシュをクリア
    torch.cuda.empty_cache()

    try:
        model.to(device)
    except RuntimeError as e:
        print(f"モデルをデバイスに移動できませんでした: {e}")
        print("CPUを使用します。")
        device = torch.device('cpu')
        model.to(device)

    # 5. 500冊のエンベディング生成
    books_synopsis = books_df['あらすじ'].tolist()
    books_embeddings = get_embeddings(books_synopsis, tokenizer, model, device, max_length=256, batch_size=8)

    if len(books_embeddings) == 0:
        print("Error: No book embeddings generated. Check the input data.")
        return []

    # 6. ユーザーの3冊のエンベディング生成
    user_synopsis = user_books_df['あらすじ'].tolist()
    user_embeddings = get_embeddings(user_synopsis, tokenizer, model, device, max_length=256, batch_size=8)

    if len(user_embeddings) == 0:
        print("Error: No user embeddings generated. Check the input data.")
        return [] 

    # 7. ユーザーのプロファイルベクトル
    user_profile = np.mean(user_embeddings, axis=0)  # 形状: (768,)
    
    # NaNをチェックし、必要に応じて置換
    if np.isnan(user_profile).any():
        print("Warning: NaN values found in user_profile. Replacing with zeros.")
        user_profile = np.nan_to_num(user_profile)

    # 8. 類似度の計算
    similarities = cosine_similarity(books_embeddings, user_profile.reshape(1, -1)).flatten()  # 形状: (500,)
    
    # NaNをチェックし、必要に応じて置換
    if np.isnan(similarities).any():
        print("Warning: NaN values found in similarities. Replacing with zeros.")
        similarities = np.nan_to_num(similarities)
    books_df['similarity'] = similarities

    # 9. 類似度の高い順に並べ替え
    books_df_sorted = books_df.sort_values(by='similarity', ascending=False).reset_index(drop=True)

    # 10. 推薦トップ20から、ユーザーが選んだ本を除外
    recommended_books = books_df_sorted[~books_df_sorted['ISBNコード'].isin(isbn_list)].head(20)

    # 11. 結果のエクスポート
    columns_to_export = ['タイトル', '著者名', '出版社名', '発売日', 'レビュー件数',
                        'レビュー平均', '価格（税込）', 'ISBNコード', '商品URL',
                        '商品画像URL', 'あらすじ', 'similarity']

    print(f"Shape of books_embeddings: {books_embeddings.shape}")
    print(f"Shape of user_embeddings: {user_embeddings.shape}")
    print(f"Shape of user_profile: {user_profile.shape}")
    save_df_to_json(recommended_books, '/code/app/json/recommended_books.json', columns=columns_to_export)
    
    # isbn_codes = recommended_books['ISBNコード'].head(3).tolist()
    # book_details = recommended_books[columns_to_export].head(3).to_dict('records')
    # return {
    #     'isbn_list': isbn_codes,
    #     'recommended_books': book_details
    # }
    # # return recommended_books['ISBNコード'].head(3).tolist()
    
    # デバッグ情報を追加
    print(f"Columns in recommended_books: {recommended_books.columns}")
    print(f"Number of rows in recommended_books: {len(recommended_books)}")

    # エラーハンドリングを追加
    try:
        isbn_codes = recommended_books['ISBNコード'].head(3).tolist()
        print(f"ISBN codes: {isbn_codes}")
        
        # 存在するカラムのみを使用
        existing_columns = [col for col in columns_to_export if col in recommended_books.columns]
        book_details = recommended_books[existing_columns].head(3).to_dict('records')
        print(f"Book details: {book_details}")
        
        return {
            'isbn_list': isbn_codes,
            'recommended_books': book_details
        }
    except Exception as e:
        print(f"Error occurred: {str(e)}")
        # エラーが発生した場合、空のリストを返す
        return {
            'isbn_list': [],
            'recommended_books': []
        }
