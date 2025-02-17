import os,sys
import argparse
import requests
import json
import struct
import datetime
from dataclasses import dataclass, field

sys.path.append(os.path.join(os.path.dirname(__file__), '.'))
from libs.ecdsa_utils import EasyEcdsaSignatureBuilder


import datetime
import json
from dataclasses import dataclass, field
from typing import ClassVar,Optional


DEFAULT_CONFIG_NAME='./jsonpost.cfg.json'

@dataclass(frozen=True)
class AppConfig:
    VERSION: ClassVar[str] = "nyatla.jp:jsonpostcl:config:1"  # ClassVarで固定値
    created_date: datetime.datetime
    private_key: bytes

    @classmethod
    def create(cls, config_file: str) -> "AppConfig":
        """AppConfigを生成し、設定ファイルに保存"""
        # 署名鍵の生成（rawエンコード、通常は 64 バイトの固定長）
        private_key = EasyEcdsaSignatureBuilder.generateKey()
        return cls(private_key=private_key, created_date=datetime.datetime.now(datetime.timezone.utc))

    @classmethod
    def load(cls, config_file: str) -> "AppConfig":
        """設定ファイルからAppConfigをロード"""
        with open(config_file, 'r') as f:
            config = json.load(f)
            assert(config["version"] == cls.VERSION)
            return cls(
                created_date=datetime.datetime.strptime(config['created_at'], '%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン情報を含む
                private_key=bytes.fromhex(config['private_key']),
            )

    def save(self, fname: str):
        """AppConfigを設定ファイルに保存"""
        config = {
            "version": self.VERSION,
            "private_key": self.private_key.hex(),
            "created_at": self.created_date.strftime('%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン付きで保存
        }
        with open(fname, 'w') as f:
            json.dump(config, f, indent=4)
    def ECDAS_NONCE_SIGN_S64P33N4(self,nonce:Optional[int])->str:
        essb=EasyEcdsaSignatureBuilder(self.private_key)
        if nonce is None:
            start_time = datetime.datetime(2000, 1, 1, 0, 0)
            current_time = datetime.datetime.now()
            elapsed_time = (current_time - start_time).total_seconds()
            # UINT32として格納できる範囲に収める
            nonce = int(elapsed_time) % (2**32)
        return essb.encode(struct.pack('>I', nonce)).hex()
    def ECDAS_NONCE_SIGN_S64P33PX(self,messgae:bytes)->str:
        essb=EasyEcdsaSignatureBuilder(self.private_key)
        return essb.encode(messgae).hex()

class CommandBase:
    def __init__(self, args):
        self.args = args

    def execute(self):
        raise NotImplementedError("Subclasses must implement the execute method")

    @classmethod
    def add_arguments(cls, subparsers):
        raise NotImplementedError("Subclasses must implement the add_arguments method")

class InitCommand(CommandBase):
    def execute(self):
        # Check if the configuration file already exists, and show two warnings
        if os.path.exists(self.args.filename):
            print(f"Warning: {self.args.filename} already exists.")
            print("This configuration file will be overwritten. Do you want to continue?")
            confirm = input("Do you want to overwrite it? (y/n): ")
            if confirm.lower() != 'y':
                print("Operation canceled.")
                return
        
        # Create a new configuration file (or overwrite)
        config = AppConfig.create(self.args.filename)
        config.save(self.args.filename)  # Save the configuration file

        print(f"The configuration file {self.args.filename} has been created.")

    @classmethod
    def add_arguments(cls, subparsers):
        parser = subparsers.add_parser("init", help="Initialize settings")
        parser.add_argument("filename", nargs='?',default=DEFAULT_CONFIG_NAME, type=str, help="Configuration file name.")
        parser.set_defaults(func=InitCommand)



DEFAULT_CONFIG_NAME = "jsonpost.cfg.json"  # 設定ファイルのデフォルト名

class UploadCommand(CommandBase):
    def execute(self):
        # 設定ファイルの読み込み
        config = AppConfig.load(self.args.config)
        
        # -fオプションが指定された場合（ファイルから読み込み）
        if self.args.filename:
            with open(self.args.filename, 'r') as f:
                json_obj = json.load(f)
        elif self.args.json:
            # -jオプションが指定された場合（直接JSON文字列）
            json_obj = json.loads(self.args.json)
        elif self.args.data:
            # upload コマンドの引数として指定された文字列を JSON として処理
            json_obj = json.loads(self.args.data)
        elif not sys.stdin.isatty():
            # 標準入力から受け取った JSON を処理
            json_obj = json.load(sys.stdin)
        else:
            print("Error: Either -f (filename), -j (JSON string), or data via stdin must be provided.")
            return
        
        # アップロードデータの準備
        data = {
            "version": "urn::nyatla.jp:json-request::ecdas-signed-upload:1",
            "signature": config.ECDAS_NONCE_SIGN_S64P33N4(self.args.nonce),
            "data": json_obj
        }
        d_json=json.dumps(data, ensure_ascii=False)

        # verbose が指定された場合、送信する JSON データを表示
        if self.args.verbose:
            print("JSON data to upload:")
            print(d_json)
        
        # ヘッダーの指定（charset=utf-8を指定）
        headers = {
            "Content-Type": "application/json; charset=utf-8"
        }

        # アップロード先のエンドポイントに対してPOSTリクエストを送信
        ep=f"{self.args.endpoint}/upload.php"
        print(f"Uploading to {ep}...")
        response = requests.post(ep, data=d_json.encode("utf-8"), headers=headers)


        # 結果の表示
        print(f"Response Status Code: {response.status_code}")
        print(f"Response Content: {response.content.decode('utf-8')}")

    @classmethod
    def add_arguments(cls, subparsers):
        # upload サブコマンドの引数設定
        upload_parser = subparsers.add_parser("upload", help="Upload JSON data")
        
        
        # upload コマンドの後に指定されるデータ
        upload_parser.add_argument("endpoint", type=str, help="The endpoint to upload the file to")
        # 排他的な引数グループを作成
        group = upload_parser.add_mutually_exclusive_group(required=False)
        group.add_argument("-f", "--filename", type=str, help="The filename to upload")
        group.add_argument("-j", "--json", type=str, help="JSON string to upload")
        upload_parser.add_argument("data", nargs='?', type=str, help="JSON string provided directly after the command")        
        upload_parser.add_argument("--config", nargs='?', type=str, default=DEFAULT_CONFIG_NAME, help="The file of client configuration.")
        upload_parser.add_argument("--nonce", type=int, required=False, default=None, help="The nonce for the upload")
        upload_parser.add_argument("--verbose", action="store_true", help="Display the JSON data being uploaded.")
       
        upload_parser.set_defaults(func=UploadCommand)




class AdminKonnichiwaCommand(CommandBase):
    def execute(self):
        # 設定ファイルの読み込み
        config = AppConfig.load(self.args.config)
        data = {
            "version": "urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1",
            "signature": config.ECDAS_NONCE_SIGN_S64P33PX("konnichiwa".encode())
        }
        d_json=json.dumps(data, ensure_ascii=False)
        # ヘッダーの指定（charset=utf-8を指定）
        headers = {
            "Content-Type": "application/json; charset=utf-8"
        }

        # アップロード先のエンドポイントに対してPOSTリクエストを送信
        ep=f"{self.args.endpoint}/heavendoor.php?konnichiwa"
        print(f"Uploading to {ep}...")
        response = requests.post(ep, data=d_json, headers=headers)


        # 結果の表示
        print(f"Response Status Code: {response.status_code}")
        print(f"Response Content: {response.content.decode('utf-8')}")        



    @classmethod
    def add_arguments(cls, subparsers):
        sp=subparsers.add_parser("konnichiwa", help="Initialize server database.")
        # upload コマンドの後に指定されるデータ
        sp.add_argument("endpoint", type=str, help="The endpoint to upload the file to")
        sp.add_argument("--config", nargs='?', type=str, default=DEFAULT_CONFIG_NAME, help="The file of client configuration.")
        sp.set_defaults(func=AdminKonnichiwaCommand)


class VersionCommand(CommandBase):
    def execute(self):
        url = f"{self.args.endpoint}/version.php"  # endpointからversion.phpにアクセス
        response = requests.get(url)
        if response.status_code == 200:
            data = response.json()
            if data["success"] == True:
                version = data["result"]["version"]
                print(f"System version: {version}")
            else:
                print("Error: Failed to fetch version.")
        else:
            print("Error: Unable to connect to the version API.")

    @classmethod
    def add_arguments(cls, subparsers):
        version_parser = subparsers.add_parser("version", help="Display version")
        version_parser.add_argument("endpoint", type=str, help="The base URL endpoint for version API")
        version_parser.set_defaults(func=VersionCommand)

def main():
    parser = argparse.ArgumentParser(description="JSONPOST CUI Client")
    subparsers = parser.add_subparsers(dest="command")

    # コマンドクラスをリストで登録
    commands = [InitCommand,UploadCommand, VersionCommand,AdminKonnichiwaCommand]

    # 各コマンドクラスの引数設定を追加
    for command in commands:
        command.add_arguments(subparsers)

    # args = parser.parse_args()
    
    # args = parser.parse_args("version http://127.0.0.1:8000/api".split(" "))
    # args = parser.parse_args("init".split(" "))
    args = parser.parse_args("upload http://127.0.0.1:8000/api {\"key\":\"valueあああ\"} --config ./jsonpost.cfg.json --verbose".split(" "))
    # args = parser.parse_args("upload http://127.0.0.1:8000/api -j {\"key\":\"valuew\"} --config ./jsonpost.cfg.json --nonce 12357 --verbose".split(" "))
    # args = parser.parse_args("upload http://127.0.0.1:8000/api -f ./jsonpost.cfg.json --config ./jsonpost.cfg.json --nonce 12349 --verbose".split(" "))
    # args = parser.parse_args("konnichiwa http://127.0.0.1:8000/api".split(" "))

    # 実行するコマンドを決定
    if args.command:
        command_class = args.func(args)
        command_class.execute()
    else:
        parser.print_help()

if __name__ == "__main__":
    main()