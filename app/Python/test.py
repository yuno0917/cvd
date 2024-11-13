import sys

def greet(name):
    return f"Hello, {name}!"

if __name__ == "__main__":
    # コマンドライン引数から名前を取得
    name = sys.argv[1] if len(sys.argv) > 1 else "World"
    # 挨拶を出力
    print(greet(name))
