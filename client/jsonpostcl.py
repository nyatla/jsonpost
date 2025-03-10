import os,sys
import time
import argparse
import requests
import json
import struct
import math
import datetime
from dataclasses import dataclass, field,replace
from typing import ClassVar,Optional,List,Callable

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





class JconpostStampedApi:
    def __init__(self,endpoint:str,pk:bytes|None,nonce:int=0,server_name:None|str=None,verbose:bool=False):
        self.endpoint=endpoint
        self.pk=pk
        self.nocne=nonce
        self.server_name=server_name
        self.verbose=verbose

    def status(self,with_update:bool=True)->requests.Response:
        headers = {
            "Content-Type": "application/json; charset=utf-8"
        }
        if self.pk is not None:
            psb=PowStampBuilder(self.pk)
            ps=psb.createStamp(0,self.server_name,None,0xffffffff)
            headers["PowStamp-1"]=ps.stamp.hex()
        # アップロード先のエンドポイントに対してPOSTリクエストを送信
        ep=f"{self.endpoint}/status.php"
        if self.verbose:
            print(f"PowStamp:{ps.stamp.hex()}")
        response = requests.get(ep, headers=headers)
        if with_update and response==200:
            try:
                j=response.json()
                if j["success"]:
                    self.server_name=j["result"]["server_name"]
                    a=j["result"]["account"]
                    if a is not None:
                        self.nonce=a["nonce"]
            except json.JSONDecodeError:
                pass
        return response
                




        ...


    def godKonnichiwa(self,pow_algolithm:str,server_name:str|None,welcome:bool,json_jcs:bool,json_schema_fpath:str|None)->requests.Response:
        """ konnichiwaを実行する。
        """
        assert(self.pk is not None)
        json_schema=None
        if json_schema_fpath is not None:
            with open(json_schema_fpath,'r',encoding='utf-8') as fp:
                json_schema=json.load(fp)
        data = {
            "version": "urn::nyatla.jp:json-request::jsonpost-konnichiwa:1",
            "params":{
                "pow_algorithm":pow_algolithm,
                "server_name":server_name, #OP
                "welcome":welcome, #OP
                'json_jcs':json_jcs,#OP
                'json_schema':json_schema #OP
            }
        }
        d_json=json.dumps(data, ensure_ascii=False).encode('utf-8')
        
        #スタンプの生成
        psb=PowStampBuilder(self.pk)
        ps=psb.createStamp(0,server_name,d_json,0xffffffff)        
        
        headers = {
            "Content-Type": "application/json; charset=utf-8",
            "PowStamp-1":ps.stamp.hex(),
        }
        if self.verbose:
            print(f"PowStamp:{ps.stamp.hex()}")

        # アップロード先のエンドポイントに対してPOSTリクエストを送信
        ep=f"{self.endpoint}/heavendoor.php?konnichiwa"

        return requests.post(ep, data=d_json, headers=headers)

    def godSetParams(self,pow_algolithm:str|None,server_name:str|None,welcome:bool,json_jcs:bool,json_schema_fpath:str|None)->requests.Response:
        """ setParamSetを実行する。
            必要なパラメータだけを設定する事。
            json_schemaだけは特殊。""の場合無効化して、Noneの場合は無視する。
        """
        assert(self.pk is not None)
        params={}
        if pow_algolithm is not None:
            params['pow_algorithm']=pow_algolithm
        if server_name is not None:
            params['server_name']=server_name
        if welcome is not None:
            params['welcome']=welcome
        if json_jcs is not None:
            params['json_jcs']=json_jcs
        if json_schema_fpath is not None:
            if json_schema_fpath=="":
                    params['json_schema']=None
            else:
                with open(json_schema_fpath,'r',encoding='utf-8') as fp:
                    js=json.load(fp)
                    params['json_schema']=js
        if len(params)==0:
            raise RuntimeError('No changes detected.')
        data = {
            "version": "urn::nyatla.jp:json-request::jsonpost-setparams:1",
            "params":params
        }
        d_json=json.dumps(data, ensure_ascii=False).encode('utf-8')
        #スタンプの生成
        psb=PowStampBuilder(self.pk)
        ps=psb.createStamp(0,self.server_name,d_json,0xffffffff)        
        # ヘッダーの指定（charset=utf-8を指定）
        headers = {
            "Content-Type": "application/json; charset=utf-8",
            "PowStamp-1":ps.stamp.hex(),
        }
        if self.verbose:
            print(f"PowStamp:{ps.stamp.hex()}")
        # アップロード先のエンドポイントに対してPOSTリクエストを送信
        ep=f"{self.endpoint}/heavendoor.php?setparams"
        return requests.post(ep, data=d_json, headers=headers)

    def upload(self,payload:str,timeout:float=2,retry:int=3,print_progress:bool=True)->requests.Response:
        target_score=0
        psb=PowStampBuilder(self.pk)
        psg=psb.createStampGenerator(self.nocne,self.server_name,payload)
        response=None
        best=0xffffffff
        best_ps=None
        if print_progress: print(f"Start hasing: target={target_score}")
        for i in range(retry):
            hash_counter=0
            if print_progress and best_ps is not None: print(f"\rScore/Hash: {best:010}/{best_ps.hash.hex()}",end="")
            with PerformanceTimer(False) as pt:
                while (pt.elapseInMs<(timeout*1000) and target_score<=best) or best_ps is None:
                    ps=next(psg)
                    hash_counter+=1
                    if ps.powScore32>=best and best_ps is not None:
                        continue
                    #found
                    best=ps.powScore32
                    best_ps=ps
                    if print_progress: print(f"\rScore/Hash: {best:010} {ps.hash.hex()}",end="")
            if print_progress: print(f"\nHashed. {hash_counter} hashes , {round(hash_counter*1000/pt.elapseInMs) if pt.elapseInMs>0 else '-'} hash/s")
            if print_progress: print(f"detected:{best_ps.hash.hex()}")
            #タイムアウト
            headers = {
                "Content-Type": "application/json; charset=utf-8",
                "PowStamp-1":best_ps.stamp.hex()
            }
            if self.verbose:
                if print_progress: print(f"PowStamp:{best_ps.stamp.hex()}")
            # アップロード先のエンドポイントに対してPOSTリクエストを送信
            ep=f"{self.endpoint}/upload.php"
            response = requests.post(ep, data=payload, headers=headers)
            try:
                j=response.json()            
                if j["success"]:
                    self.nocne=j["result"]["account"]["nonce"]
                    #成功
                    return response
                else:
                    code=j["error"]["code"]
                    if code==205:
                        #nonce,目標Powを設定してハッシング
                        target_score=j["error"]["hint"]["required_score"]
                        if print_progress: print(f"205 error: retarget to {target_score}. retry({i+1}/{retry})")
                        continue
                    else:
                        pass
            except json.JSONDecodeError:
                pass
        #失敗お
        return response






class ResponseFormatter:
    def __init__(self,response:requests.Response):
        self.response=response
    def print(self,pp:bool=True):
        r=self.response
        print(f"HTTP Status Code: {r.status_code}")
        if pp:
            raw=r.content.decode('utf-8')
            try:
                d=json.dumps(json.loads(raw),indent=4,ensure_ascii=True)
                print(f"Response Content: {d}")
            except:
                print(f"Response Content: {r.content.decode('utf-8')}")
        else:
            print(f"Response Content: {r.content.decode('utf-8')}")




def str_to_bool(value: str) -> bool:
    value = value.lower()
    if value in ['yes', 'true', '1']: return True
    if value in ['no', 'false', '0']: return False
    raise argparse.ArgumentTypeError(f"Invalid value '{value}', must be one of [yes, true, 1, no, false, 0].")



        


class JsonpostCl:
    DEFAULT_CONFIG_NAME='./jsonpost.cfg.json'



    @dataclass(frozen=True)
    class AppConfig:
        VERSION: ClassVar[str] = "nyatla.jp:jsonpostcl:config:1"  # ClassVarで固定値
        created_date: datetime.datetime
        private_key: bytes
        params_nonce:int
        params_server_name:Optional[str]

        @classmethod
        def create(cls) -> "JsonpostCl.AppConfig":
            """AppConfigを生成し、設定ファイルに保存"""
            # 署名鍵の生成（rawエンコード、通常は 32 バイトの固定長）
            private_key = EcdsaSignner.generateKey()
            return cls(
                created_date=datetime.datetime.now(datetime.timezone.utc),
                private_key=private_key,
                params_nonce=0,
                params_server_name=None)

        @classmethod
        def load(cls, config_file: str) -> "JsonpostCl.AppConfig":
            """設定ファイルからAppConfigをロード"""
            with open(config_file, 'r') as f:
                config = json.load(f)
                assert(config["version"] == cls.VERSION)
                return cls(
                    created_date=datetime.datetime.strptime(config['created_at'], '%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン情報を含む
                    private_key=bytes.fromhex(config['private_key']),
                    params_server_name=config['params']['server_name'],
                    params_nonce=config['params']['nonce']
                )

        def save(self, fname: str):
            """AppConfigを設定ファイルに保存"""
            config = {
                "version": self.VERSION,
                "private_key": self.private_key.hex(),
                "created_at": self.created_date.strftime('%Y-%m-%dT%H:%M:%S%z'),  # タイムゾーン付きで保存
                "params":{
                    "server_name":self.params_server_name,
                    "nonce":self.params_nonce,
                },
            }
            with open(fname, 'w') as f:
                json.dump(config, f, indent=4)
        
        def setNonce(self,nonce:int)->"JsonpostCl.AppConfig":
            return replace(self, params_nonce=nonce)
        def setServerName(self,server_name:Optional[str])->"JsonpostCl.AppConfig":
            return replace(self, params_server_name=server_name)

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

            api=JconpostStampedApi(
                self.args.endpoint,
                config.private_key,
                nonce=config.params_nonce+1 if self.args.nonce is None else self.args.nonce,#現在の値+1で設定
                server_name=config.params_server_name if self.args.server_name is None else self.args.server_name)

            def hasher_callback(msg:str):
                print(msg)
            ret=api.upload(data,self.args.timeout,self.args.rounds,hasher_callback)
            # if self.args.verbose:
            #     print("Upload data :")
            #     print(f"X-PowStamp :",espow.stamp.hex())
            #     print(f"HASH :",espow.hash.hex())
            #     print("JSON :")
            #     print(data.decode())


                # # ハッシュ処理
                # espow = config.generatePoWStamp(self.args.nonce,self.args.server_name ,data,powtarget)            
                # pownonce = espow.powNonceAsInt  # ハッシュ値（または結果）
                # elapsed_time=pf.elapseInMs
                # # ハッシュレートを計算 (ハッシュ数/秒)
                # hash_rate = pownonce / elapsed_time if elapsed_time > 0 else 0
                # # 結果をプリント
                # # print(espow.powbits,espow.sha256d.hex())                
                # print(f"accepted: {espow.powScore32}/{pownonce} ({espow.powScore32*100/(32-math.log2(powtarget)):.2f}%) {round(hash_rate)}hash/s (yay!!!)")


            fmt=ResponseFormatter(ret)
            fmt.print()
            #ここから先は返却値がおかしければエラーでるよ
            j=ret.json()
            if j["success"]:
                config=config.setNonce(j["result"]["account"]["nonce"])
                #configの更新
                print(f"Config file updated.")
                config.save(self.args.config)            
            


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
            upload_parser.add_argument("--normalize", type=str, choices=['raw','jcs','json'],required=False, default='jcs', help="Formatting before sending.")
            upload_parser.add_argument("--timeout", type=float, default=5.0,required=False, help="Hashing timeout per each round in second.")
            upload_parser.add_argument("--rounds", type=int, default=3,required=False, help="Hashing rounds")
            upload_parser.add_argument("--verbose", action="store_true", help="Display the JSON data being uploaded.")
        
            upload_parser.set_defaults(func=JsonpostCl.UploadCommand)




    class AdminKonnichiwaCommand(CommandBase):
        def execute(self):
            
            # 設定ファイルの読み込み
            config = JsonpostCl.AppConfig.load(self.args.config)

            api=JconpostStampedApi(
                self.args.endpoint,
                config.private_key,
                server_name=self.args.server_name)
            # # アップロード先のエンドポイントに対してPOSTリクエストを送信
            # ep=f"{self.args.endpoint}/heavendoor.php?konnichiwa"
            ret=api.godKonnichiwa(
                self.args.pow_algorithm,
                self.args.server_name,
                self.args.welcome,
                self.args.json_jcs,
                self.args.json_schema
            )
            fmt=ResponseFormatter(ret)
            fmt.print()
            #ここから先は返却値がおかしければエラーでるよ
            j=ret.json()
            if j["success"]:
                config=config.setServerName(j["result"]["server_name"]).setNonce(0)
                #configの更新
                print(f"Config file updated.")
                config.save(self.args.config)
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
            config = JsonpostCl.AppConfig.load(self.args.config)

            api=JconpostStampedApi(
                self.args.endpoint,
                config.private_key,
                server_name=config.params_server_name)
            # # アップロード先のエンドポイントに対してPOSTリクエストを送信
            # ep=f"{self.args.endpoint}/heavendoor.php?konnichiwa"
            ret=api.godSetParams(
                self.args.pow_algorithm,
                self.args.server_name,
                self.args.welcome,
                self.args.json_jcs,
                "" if self.args.json_no_schema else self.args.json_schema #無効の場合はNone,指定があればそれを指定。
            )
            fmt=ResponseFormatter(ret)
            fmt.print()
            #ここから先は返却値がおかしければエラーでるよ
            j=ret.json()
            if j["success"]:
                config=config.setServerName(j["result"]["server_name"])
                #configの更新
                print(f"Config file updated.")
                config.save(self.args.config)



        @classmethod
        def add_arguments(cls, subparsers):

            sp=subparsers.add_parser("setparams", help="Initialize server database.")
            # upload コマンドの後に指定されるデータ
            sp.add_argument("endpoint", type=str, help="The endpoint to upload the file to")
            sp.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            sp.add_argument("--pow-algorithm", type=str, default=None, help="Pow difficulty detection algorithm.('[\"tlsln\",[10,16,0.8]]')")
            sp.add_argument("--server-name", type=str, nargs="?", const="", default=None, help="New server domain name.")
            sp.add_argument("--welcome", type=str_to_bool, required=False, default=None, help="Accept new accounts.['true','false','0','1','yes','no']")
            sp.add_argument("--json-jcs", type=str_to_bool, required=False, default=None, help="Accept JCS format only.['true','false','0','1','yes','no']")

            # 排他関係を設定 (両方が指定されないことを許容)
            group = sp.add_mutually_exclusive_group(required=False)
            group.add_argument("--json-no-schema", action='store_true', help="Disable JSON schema validation.")
            group.add_argument("--json-schema", type=str, required=False, default=None, help="Json schema filename")

            sp.set_defaults(func=JsonpostCl.AdminSetparamsCommand)



    class StatusCommand(CommandBase):
        def execute(self):
            verbose=self.args.verbose
            config = JsonpostCl.AppConfig.load(self.args.config)

            api=JconpostStampedApi(
                self.args.endpoint,
                config.private_key if self.args.account else None,
                server_name=config.params_server_name if self.args.server_name is None else self.args.server_name,
                verbose=verbose)

            ret=api.status(with_update=False)

            fmt=ResponseFormatter(ret)
            fmt.print()
            #ここから先は返却値がおかしければエラーでるよ
            j=ret.json()
            if j["success"] and self.args.upgrade:
                config=config.setServerName(j["result"]["settings"]["server_name"])
                a=j["result"]["account"]
                if a is not None:
                    config=config.setNonce(a["nonce"])
                print(f"Config file updated.")
                #configの更新
                config.save(self.args.config)

        @classmethod
        def add_arguments(cls, subparsers):
            sp = subparsers.add_parser("status", help="Display status and upgrade local confuration.")
            sp.add_argument("endpoint", type=str, help="The base URL endpoint for status API")
            sp.add_argument("-C","--config", nargs='?', type=str, default=JsonpostCl.DEFAULT_CONFIG_NAME, help="The file of client configuration.")
            sp.add_argument("-S","--server-name", default=None, type=str, help="Temporary override server name. default=None(public)")
            sp.add_argument("-A","--account", action="store_true", help="Enable account infomation.")
            sp.add_argument("-U","--upgrade", action="store_true", help="Updates the configuration file to match the server. Use -A to also update the account information.")
            sp.add_argument("--verbose", action="store_true", help="Display the JSON data being uploaded.")
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