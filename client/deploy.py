""" クライアントをパッケージングする貯めのスクリプト。
    サーバーのclientディレクトリに

"""
import os
import zipfile

def zip_files(start_dir, zip_name):
    # ZIPファイルを作成
    with zipfile.ZipFile(zip_name, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(start_dir):
            # __pycache__ディレクトリとtestディレクトリを除外
            if '__pycache__' in dirs:
                dirs.remove('__pycache__')
            if 'test' in dirs:
                dirs.remove('test')

            for file in files:
                # .pyファイルのみ追加、スクリプト自身を除外
                if file.endswith('.py') and file != os.path.basename(__file__):
                    file_path = os.path.join(root, file)
                    arcname = os.path.relpath(file_path, start_dir)
                    zipf.write(file_path, arcname=arcname)

if __name__ == '__main__':
    # スクリプトの置かれているディレクトリを作業ディレクトリに設定
    start_directory = os.path.dirname(os.path.abspath(__file__))  # スクリプトが置かれているディレクトリ
    zip_filename = os.path.join(start_directory, "../server/public/client/jsonpost-client-py.zip")  # 作成するZIPファイルの名前
    
    zip_files(start_directory, zip_filename)
    print(f"ZIPファイル {zip_filename} が作成されました。")
