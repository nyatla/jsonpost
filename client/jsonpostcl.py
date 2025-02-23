import os,sys
import time
import argparse
import requests
import json
import struct
import datetime
from dataclasses import dataclass, field
from typing import ClassVar,Optional,List

sys.path.append(os.path.join(os.path.dirname(__file__), '.'))
from libs.powstamp import PowStampBuilder,PowStamp
from libs.ecdsa_utils import EcdsaSignner



import time

class PerformanceTimer(object):
    def __init__(self, verbose=True):
        self.verbose = verbose

    def __enter__(self):
        self.start = time.time()
        return self

    def __exit__(self, *args):
        self.end = time.time()
        self.secs = self.end - self.start
        self.msecs = self.secs * 1000  # millisecs
        if self.verbose:
            print('elapsed time: %f ms' %(self.msecs))
    @property
    def elapseInMs(self)->int:
        end = time.time()
        secs = end - self.start
        return round(secs * 1000)  # millisecs
        



    
        
        


class JsonpostCl:
    DEFAULT_CONFIG_NAME='./jsonpost.cfg.json'
    # class ServerItem:
    #     last_nonce:int
    #     write_pow_bits:int
    # class ServerList:
    #     items:List[ServerItem]
    #     def getByName(self,)

    @dataclass(frozen=True)
    class AppConfig:
        VERSION: ClassVar[str] = "nyatla.jp:jsonpostcl:config:1"  # ClassVarで固定値
        created_date: datetime.datetime
        private_key: bytes
        params_pow_bits:int

        @classmethod
        def create(cls) -> "JsonpostCl.AppConfig":
            """AppConfigを生成し、設定ファイルに保存"""
            # 署名鍵の生成（rawエンコード、通常は 32 バイトの固定長）
            private_key = EcdsaSignner.generateKey()
            return cls(created_date=datetime.datetime.now(datetime.timezone.utc),private_key=private_key,params_pow_bits=0)

        @classmethod
        def load(cls, config_file: str) -> "JsonpostCl.AppConfig":
            """設定ファイルからAppConfigをロード"""
            with open(config_file, 'r') as f:
                config = json.load(f)
                assert(config["version"] == cls.VERSION)
                return cls(
                    created_date=datetime.datetime.strptime(config['created_at'], '%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン情報を含む
                    private_key=bytes.fromhex(config['private_key']),
                    params_pow_bits=config['params']['default_powbits']
                )

        def save(self, fname: str):
            """AppConfigを設定ファイルに保存"""
            config = {
                "version": self.VERSION,
                "private_key": self.private_key.hex(),
                "created_at": self.created_date.strftime('%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン付きで保存
                "params":{
                    "default_powbits":self.params_pow_bits
                },
            }
            with open(fname, 'w') as f:
                json.dump(config, f, indent=4)
        def generatePoWStamp(self,nonce:Optional[int],server_domain:Optional[str],payload:Optional[bytes],target_pow_score:int)->PowStamp:
            psb=PowStampBuilder(self.private_key)
            if nonce is None:
                start_time = datetime.datetime(2000, 1, 1, 0, 0)
                current_time = datetime.datetime.now()
                elapsed_time = (current_time - start_time).total_seconds()
                # UINT32として格納できる範囲に収める
                nonce = int(elapsed_time) % (2**32)
            return psb.encode(nonce,server_domain,payload,target_pow_score)


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
            config = JsonpostCl.AppConfig.create()
            config.save(self.args.filename)  # Save the configuration file

            print(f"The configuration file {self.args.filename} has been created.")

        @classmethod
        def add_arguments(cls, subparsers):
            parser = subparsers.add_parser("init", help="Initialize settings")
            parser.add_argument("filename", nargs='?',default=JsonpostCl.DEFAULT_CONFIG_NAME, type=str, help="Configuration file name.")
            parser.set_defaults(func=JsonpostCl.InitCommand)




    class UploadCommand(CommandBase):
        def execute(self):
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)
            
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


            d_json=json.dumps(json_obj, ensure_ascii=False).encode('utf-8')


            powbitstarget=config.params_pow_bits if self.args.powbits==0 else self.args.powbits
            print(f"Target Pow bits:{powbitstarget}")
            print(f"Start hashing!")
            espow:PowStamp=None
            with PerformanceTimer() as pf:
                # ハッシュ処理
                espow = config.generatePoWStamp(self.args.nonce,self.args.server_name ,d_json,powbitstarget)            
                pownonce = espow.powNonceAsInt  # ハッシュ値（または結果）
                elapsed_time=pf.elapseInMs
                # ハッシュレートを計算 (ハッシュ数/秒)
                hash_rate = pownonce / elapsed_time if elapsed_time > 0 else 0
                # 結果をプリント
                # print(espow.powbits,espow.sha256d.hex())
                print(f"accepted: {espow.score}/{pownonce} ({espow.score*100/powbitstarget:.2f}%) {round(hash_rate)}hash/s (yay!!!)")


            # verbose が指定された場合、送信する JSON データを表示
            if self.args.verbose:
                print("JSON data to upload:")
                print(f"X-PowStamp",espow.stamp.hex())
                print(d_json.decode())
            
            # ヘッダーの指定（charset=utf-8を指定）
            headers = {
                "Content-Type": "application/json; charset=utf-8",
                "PowStamp-1":espow.stamp.hex()
            }

            # アップロード先のエンドポイントに対してPOSTリクエストを送信
            ep=f"{self.args.endpoint}/upload.php"
            print(f"Uploading to {ep}...")
            response = requests.post(ep, data=d_json, headers=headers)


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
            group.add_argument("-F", "--filename", type=str, help="The filename to upload")
            group.add_argument("-J", "--json", type=str, help="JSON string to upload")
            upload_parser.add_argument("data", nargs='?', type=str, help="JSON string provided directly after the command")        
            upload_parser.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            upload_parser.add_argument("-S","--server-name", default=None, type=str, help="New server domain name. default=None(public)")
            upload_parser.add_argument("--nonce", type=int, required=False, default=None, help="The nonce for the upload")
            upload_parser.add_argument("--powbits", type=int, required=False, default=0, help="Number of PoW bits required for verification.")
            upload_parser.add_argument("--verbose", action="store_true", help="Display the JSON data being uploaded.")
        
            upload_parser.set_defaults(func=JsonpostCl.UploadCommand)




    class AdminKonnichiwaCommand(CommandBase):
        def execute(self):
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)
            data = {
                "version": "urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1",
                "params":{
                    "pow_bits_write":self.args.params_powbits_write,
                    "pow_bits_read":self.args.params_powbits_read,
                    "server_name":self.args.server_name
                }
            }
            d_json=json.dumps(data, ensure_ascii=False).encode('utf-8')
            #スタンプの生成
            ps:PowStamp=config.generatePoWStamp(0,self.args.server_name,d_json,0)
            # ヘッダーの指定（charset=utf-8を指定）
            headers = {
                "Content-Type": "application/json; charset=utf-8",
                "PowStamp-1":ps.stamp.hex(),
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
            sp.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            sp.add_argument("-S","--server-name", default=None, type=str, help="New server domain name. default=None(public)")
            sp.add_argument("--params-powbits-write", default=0, type=int, help="Server setting parametor. Number of hasing difficulity bits.")
            sp.add_argument("--params-powbits-read", default=0, type=int, help="Server setting parametor. Number of hasing difficulity bits.")
            sp.set_defaults(func=JsonpostCl.AdminKonnichiwaCommand)


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
            version_parser.set_defaults(func=JsonpostCl.VersionCommand)

    @staticmethod
    def main(args:List[str]):

        # コマンドクラスをリストで登録
        commands = [
            JsonpostCl.InitCommand,
            JsonpostCl.UploadCommand,
            JsonpostCl.VersionCommand,
            JsonpostCl.AdminKonnichiwaCommand
        ]
        parser = argparse.ArgumentParser(description="JSONPOST CUI Client")
        subparsers = parser.add_subparsers(dest="command")

        # 各コマンドクラスの引数設定を追加
        for command in commands:
            command.add_arguments(subparsers)

        parsed = parser.parse_args(args)
        # 実行するコマンドを決定
        if parsed.command:
            command_class = parsed.func(parsed)
            command_class.execute()
        else:
            parser.print_help()


if __name__ == "__main__":
    JsonpostCl.main()