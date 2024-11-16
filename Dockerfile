# syntax = docker/dockerfile:1.5

# 使用する基本イメージ
FROM python:3.10-slim

# 作業ディレクトリを設定
WORKDIR /code

# システムの依存関係をインストール
RUN apt-get update && apt-get install -y \
    libgl1-mesa-glx \
    libglib2.0-0 \
    libzbar0 \
    v4l-utils \
    ffmpeg \
    libsm6 \
    libxext6 \
    && rm -rf /var/lib/apt/lists/*

# requirements.txtをコピーしてrequestsをインストール
COPY ./requirements.txt /code/requirements.txt

RUN pip install --no-cache-dir --upgrade -r /code/requirements.txt
# pipのキャッシュを使用してパッケージをインストール
# RUN --mount=type=cache,target=/root/.cache/pip \
    # pip install --upgrade -r /code/requirements.txt

# LaravelプロジェクトのPythonスクリプトをコピー
COPY ./app/Python /code/app/Python
# モデルファイルをコピー
COPY ./app/Python/models /code/app/Python/models

# CMD echo "hello world"
CMD ["uvicorn", "app.Python.main:app", "--reload", "--host", "0.0.0.0", "--port", "8080"]
