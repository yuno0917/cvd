<!-- resources/views/cvds/strong.blade.php -->

@extends('layouts.app')

@section('title', '色覚補正')

@section('content')
    <div class="container">
        <h2>色覚補正（Daltonize）</h2>

        <!-- エラーメッセージの表示 -->
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <!-- Daltonize 画像アップロードフォーム -->
        <form action="{{ route('daltonize.image') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="image">補正したい画像を選択:</label>
                <input type="file" name="image" id="image" class="form-control" required>
            </div>
            <div class="form-group mt-3">
                <label for="color_deficit">色覚異常のタイプ:</label>
                <select name="color_deficit" id="color_deficit" class="form-control" required>
                    <option value="d">赤色盲 (Deuteranopia)</option>
                    <option value="p">緑色盲 (Protanopia)</option>
                    <option value="t">青色盲 (Tritanopia)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mt-3">色覚補正を実行</button>
        </form>

        <!-- デバッグ情報の表示 -->
        @isset($correctedImage)
            <h4 class="mt-4">補正後の画像データ長: {{ strlen($correctedImage) }}</h4>
        @endisset

        <!-- 補正後の画像表示 -->
        @isset($correctedImage)
            <h4 class="mt-4">補正後の画像:</h4>
            <img src="data:image/png;base64,{{ $correctedImage }}" alt="Corrected Image" class="img-fluid">
        @endisset
    </div>
@endsection
