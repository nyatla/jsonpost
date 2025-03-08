import os,sys
import time
import argparse
import requests
import json
import struct
import math
import datetime
from dataclasses import dataclass, field
from typing import ClassVar,Optional,List

sys.path.append(os.path.join(os.path.dirname(__file__), '.'))
from libs.powstamp import PowStampBuilder,PowStamp
from libs.ecdsa_utils import EcdsaSignner
from libs.jcs import JCSSerializer



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
        


def str_to_bool(value: str) -> bool:
    value = value.lower()
    if value in ['yes', 'true', '1']: return True
    if value in ['no', 'false', '0']: return False
    raise argparse.ArgumentTypeError(f"Invalid value '{value}', must be one of [yes, true, 1, no, false, 0].")



        


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
        params_pow_target:int

        @classmethod
        def create(cls) -> "JsonpostCl.AppConfig":
            """AppConfigを生成し、設定ファイルに保存"""
            # 署名鍵の生成（rawエンコード、通常は 32 バイトの固定長）
            private_key = EcdsaSignner.generateKey()
            return cls(created_date=datetime.datetime.now(datetime.timezone.utc),private_key=private_key,params_pow_target=0x0fffffff)

        @classmethod
        def load(cls, config_file: str) -> "JsonpostCl.AppConfig":
            """設定ファイルからAppConfigをロード"""
            with open(config_file, 'r') as f:
                config = json.load(f)
                assert(config["version"] == cls.VERSION)
                return cls(
                    created_date=datetime.datetime.strptime(config['created_at'], '%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン情報を含む
                    private_key=bytes.fromhex(config['private_key']),
                    params_pow_target=config['params']['default_pow_target']
                )

        def save(self, fname: str):
            """AppConfigを設定ファイルに保存"""
            config = {
                "version": self.VERSION,
                "private_key": self.private_key.hex(),
                "created_at": self.created_date.strftime('%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン付きで保存
                "params":{
                    "default_pow_target":self.params_pow_target
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
        """ クライアントのコンフィギュレーションファイルを作成する。
        """
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
        """ JSONをアップロードする
        """
        def execute(self):
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)

            #テキストデータを得る
            data=None
            if self.args.filename:
                with open(self.args.filename, 'r') as f:
                    data=f.read()
            elif self.args.json:
                # -jオプションが指定された場合（直接JSON文字列）
                data=self.args.json
            elif not sys.stdin.isatty():
                # 標準入力から受け取った JSON を処理
                data = sys.stdin
            else:
                print("Error: Either -f (filename), -j (JSON string), or data via stdin must be provided.")
                return
            #成型
            if self.args.normalize=='json':
                data=json.dumps(json.loads(data))
            elif self.args.normalize=='jcs':
                jcss=JCSSerializer()
                data=jcss.dumps(json.loads(data))
            else:
                pass
            data=data.encode('utf-8')
            
            powtarget=config.params_pow_target if self.args.powtarget==0 else self.args.powtarget
            print(f"Target Pow score:{powtarget}")
            print(f"Start hashing!")
            espow:PowStamp=None
            with PerformanceTimer() as pf:
                #ここはｶｯｺｲｲHasherにしたい。

                # ハッシュ処理
                espow = config.generatePoWStamp(self.args.nonce,self.args.server_name ,data,powtarget)            
                pownonce = espow.powNonceAsInt  # ハッシュ値（または結果）
                elapsed_time=pf.elapseInMs
                # ハッシュレートを計算 (ハッシュ数/秒)
                hash_rate = pownonce / elapsed_time if elapsed_time > 0 else 0
                # 結果をプリント
                # print(espow.powbits,espow.sha256d.hex())                
                print(f"accepted: {espow.powScore32}/{pownonce} ({espow.powScore32*100/(32-math.log2(powtarget)):.2f}%) {round(hash_rate)}hash/s (yay!!!)")


            # verbose が指定された場合、送信する JSON データを表示
            if self.args.verbose:
                print("Upload data :")
                print(f"X-PowStamp :",espow.stamp.hex())
                print(f"HASH :",espow.hash.hex())
                print("JSON :")
                print(data.decode())
            
            # ヘッダーの指定（charset=utf-8を指定）
            headers = {
                "Content-Type": "application/json; charset=utf-8",
                "PowStamp-1":espow.stamp.hex()
            }

            # アップロード先のエンドポイントに対してPOSTリクエストを送信
            ep=f"{self.args.endpoint}/upload.php"
            print(f"Uploading to {ep}...")
            response = requests.post(ep, data=data, headers=headers)


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
            upload_parser.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            upload_parser.add_argument("-S","--server-name", default=None, type=str, help="New server domain name. default=None(public)")
            upload_parser.add_argument("-N","--nonce", type=int, required=False, default=None, help="The nonce for the upload")
            upload_parser.add_argument("-P","--powtarget", type=int, required=False, default=0x0fffffff, help="Target PoW score.")
            upload_parser.add_argument("--normalize", type=str, choices=['raw','jcs','json'],required=False, default='jcs', help="Formatting before sending.")
            upload_parser.add_argument("--verbose", action="store_true", help="Display the JSON data being uploaded.")
        
            upload_parser.set_defaults(func=JsonpostCl.UploadCommand)




    class AdminKonnichiwaCommand(CommandBase):
        def execute(self):
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)

            json_schema=None
            if self.args.json_schema is not None:
                with open(self.args.json_schema,'r',encoding='utf-8') as fp:
                    json_schema=json.load(fp)

            data = {
                "version": "urn::nyatla.jp:json-request::jsonpost-konnichiwa:1",
                "params":{
                    "pow_algorithm":self.args.pow_algorithm,
                    "server_name":self.args.server_name,
                    "welcome":self.args.welcome,
                    'json_jcs':self.args.json_jcs,
                    'json_schema':json_schema
                }
            }



            d_json=json.dumps(data, ensure_ascii=False).encode('utf-8')
            #スタンプの生成
            ps:PowStamp=config.generatePoWStamp(0,self.args.server_name,d_json,0xffffffff)
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
            sp.add_argument("--pow-algorithm", type=str, required=False, default='["tlsln",[10,16,0.8]]', help="Pow difficulty detection algorithm.")
            sp.add_argument("--welcome", type=str_to_bool, required=False, default=None, help="Accept new accounts.['true','false','0','1','yes','no']")
            sp.add_argument("--json-jcs", type=str_to_bool, required=False, default=None, help="Accept JCS format only.['true','false','0','1','yes','no']")
            sp.add_argument("--json-schema", type=str, required=False, default=None, help="Json schema filename")
            sp.set_defaults(func=JsonpostCl.AdminKonnichiwaCommand)


    class AdminSetparamsCommand(CommandBase):
        def execute(self):
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)
            params={}
            if self.args.pow_algorithm is not None:
                params['pow_algorithm']=self.args.pow_algorithm
            if self.args.new_server_name is not None:
                params['server_name']=self.args.new_server_name if len(self.args.new_server_name)>0 else None
            if self.args.welcome is not None:
                params['welcome']=self.args.welcome
            if self.args.json_jcs is not None:
                params['json_jcs']=self.args.json_jcs
            if self.args.json_schema is not None:
                with open(self.args.json_schema,'r',encoding='utf-8') as fp:
                    js=json.load(fp)
                    params['json_schema']=js
            if len(params)==0:
                print('No changes detected.')
                return
            print(params)
            
            data = {
                "version": "urn::nyatla.jp:json-request::jsonpost-setparams:1",
                "params":params
            }
            d_json=json.dumps(data, ensure_ascii=False).encode('utf-8')
            #スタンプの生成
            ps:PowStamp=config.generatePoWStamp(0,self.args.server_name,d_json,0xffffffff)
            # ヘッダーの指定（charset=utf-8を指定）
            headers = {
                "Content-Type": "application/json; charset=utf-8",
                "PowStamp-1":ps.stamp.hex(),
            }

            # アップロード先のエンドポイントに対してPOSTリクエストを送信
            ep=f"{self.args.endpoint}/heavendoor.php?setparams"
            print(f"Uploading to {ep}...")
            response = requests.post(ep, data=d_json, headers=headers)

            # 結果の表示
            print(f"Response Status Code: {response.status_code}")
            print(f"Response Content: {response.content.decode('utf-8')}")        



        @classmethod
        def add_arguments(cls, subparsers):

            sp=subparsers.add_parser("setparams", help="Initialize server database.")
            # upload コマンドの後に指定されるデータ
            sp.add_argument("endpoint", type=str, help="The endpoint to upload the file to")
            sp.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            sp.add_argument("-S","--server-name", default=None, type=str, help="Current server domain name. default=None(public)")
            sp.add_argument("--pow-algorithm", type=str, default=None, help="Pow difficulty detection algorithm.('[\"tlsln\",[10,16,0.8]]')")
            sp.add_argument("--new-server-name", type=str, nargs="?", const="", default=None, help="New server domain name.")
            sp.add_argument("--welcome", type=str_to_bool, required=False, default=None, help="Accept new accounts.['true','false','0','1','yes','no']")
            sp.add_argument("--json-jcs", type=str_to_bool, required=False, default=None, help="Accept JCS format only.['true','false','0','1','yes','no']")
            sp.add_argument("--json-schema", type=str, required=False, default=None, help="Json schema filename")
            sp.set_defaults(func=JsonpostCl.AdminSetparamsCommand)



    class StatusCommand(CommandBase):
        def execute(self):
            url = f"{self.args.endpoint}/status.php"  # endpointからversion.phpにアクセス
            response=None
            if self.args.account:
                config = JsonpostCl.AppConfig.load(self.args.config)
                powstamp=config.generatePoWStamp(299,self.args.server_name,None,0xffffffff)
                headers = {
                    "PowStamp-1":powstamp.stamp.hex(),
                }
                response = requests.get(url,headers=headers)
            else:
                response = requests.get(url)
            print(response.content.decode('utf-8'))

        @classmethod
        def add_arguments(cls, subparsers):
            sp = subparsers.add_parser("status", help="Display status")
            sp.add_argument("endpoint", type=str, help="The base URL endpoint for status API")
            sp.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            sp.add_argument("-S","--server-name", default=None, type=str, help="New server domain name. default=None(public)")
            sp.add_argument("-A","--account", action="store_true", help="Enable account infomation.")
            sp.set_defaults(func=JsonpostCl.StatusCommand)
    @staticmethod
    def main(args:List[str]):

        # コマンドクラスをリストで登録
        commands = [
            JsonpostCl.InitCommand,
            JsonpostCl.UploadCommand,
            JsonpostCl.AdminKonnichiwaCommand,
            JsonpostCl.StatusCommand,
            JsonpostCl.AdminSetparamsCommand,
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
    JsonpostCl.main(sys.argv[1:])