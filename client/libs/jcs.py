

from typing import Iterator, Iterable, Sequence, Union
import json
from decimal import Decimal
from typing import Any

class JCSSerializer:
    def __init__(self):
        pass

    def _jcs_sort_key(self, key: str) -> bytes:
        return key.encode('utf-8')

    def _normalize_number(self, value):
        """
        数値の正規化
        - 1.0 => "1"
        - 不要な指数表記を排除
        - Decimalに変換して正規化し、文字列化
        """
        if isinstance(value, float) or isinstance(value, Decimal):
            decimal_value = Decimal(str(value))
            if decimal_value == decimal_value.to_integral():
                return str(int(decimal_value))  # 整数化（"1"）
            return format(decimal_value.normalize(), 'f')  # 小数形式に（"1.23"など）
        return value

    def _prepare_jcs(self, obj: Any) -> Any:
        """
        オブジェクトのキー順ソート＋数値正規化
        """
        if isinstance(obj, dict):
            return {
                k: self._prepare_jcs(obj[k])
                for k in sorted(obj.keys(), key=self._jcs_sort_key)
            }
        elif isinstance(obj, list):
            return [self._prepare_jcs(v) for v in obj]
        else:
            return self._normalize_number(obj)

    def dumps(self, obj: Any) -> str:
        """
        JCS準拠JSON生成
        """
        prepared = self._prepare_jcs(obj)

        def decimal_default(o):
            if isinstance(o, Decimal):
                return str(o)
            raise TypeError(f"Type {type(o)} not serializable")

        return json.dumps(
            prepared,
            ensure_ascii=False,
            separators=(',', ':'),
            sort_keys=False,
            default=decimal_default  # Decimalをそのまま文字列出力
        )
    
class CharIterator(Iterator[str]):
    def __init__(self, s: Union[Iterable[str], Iterator[str], Sequence[str]]):
        self.p = 0
        if isinstance(s, (list, tuple, str)):  
            self.s = list(s)  # Sequenceならそのままリスト化
        else:
            self.s = [next(s)]  # Iterable/Iteratorなら最初の1文字だけ取得

    def __next__(self) -> str:
        if self.p >= len(self.s):
            raise StopIteration()
        c = self.s[self.p]
        self.p += 1
        return c


class InvalidJcsException(Exception):

    def __init__(self, iter: CharIterator):
        if isinstance(iter.s, list) and len(iter.s) > 1:
            error_context = iter.s[max(0, iter.p-10):iter.p]  # Sequenceなら直前10文字
        else:
            error_context = iter.s[0]  # Iterable/Iteratorなら最初の1文字のみ表示
        super().__init__(f"Error {iter.p} at {error_context}")


class JCSValidator:
    """ JSON Canonicalization Scheme (JCS)の簡易バリデータです。
    https://datatracker.ietf.org/doc/html/rfc8785
    以下の部分については実装していません。
    使いどころはJSONハッシュ生成前の事前チェックなどです。
    - 数値の長さ
    - \\u、\\xの正当性チェック
    """    
    # 入力されたJSONがJCS準拠かを判定
    def isJcsToken(self, iter:CharIterator):
        c=next(iter)
        if c=='{':
            #object
            self.isValidObject(iter)
        elif c=='[':
            #array
            self.isValidArray(iter)
        else:
            raise InvalidJcsException(iter)

    def isValidArray(self,iter:CharIterator)->bool:
        last_c=None
        while True:
            c=next(iter)
            if c=='"':#文字列
                self.isValidString(iter)
                c=next(iter)
            elif c=='{':#object
                self.isValidObject(iter)
                c=next(iter)
            elif c=='[':#array
                self.isValidArray(iter)
                c=next(iter)
            elif c in 'tfn':
                self.isValidLiteral(c, iter)                
                c=next(iter)
            elif c==']':
                if last_c==',':
                    raise InvalidJcsException(iter)
            else:
                c=self.isValidNumber(c,iter)
            if c==']':
                return True
            elif c!=',':
                raise InvalidJcsException(iter)
            last_c=c

    def isValidSet(self,iter:CharIterator)->str:
        k=self.isValidKey(iter)
        if next(iter)!=':':
            raise InvalidJcsException(iter)
        c=next(iter)
        if c=='"':#文字列
            self.isValidString(iter)
            c=next(iter)
        elif c=='{':#object
            self.isValidObject(iter)
            c=next(iter)
        elif c=='[':#array
            self.isValidArray(iter)
            c=next(iter)
        elif c in 'tfn':
            self.isValidLiteral(c, iter)                
            c=next(iter)
        else: #number
            c=self.isValidNumber(c,iter)
        return c,k


    def isValidObject(self,iter:CharIterator):
        last_c=None        
        last_key=None
        while True:
            c=next(iter)
            if c=='"':#文字列
                c,k=self.isValidSet(iter)
                if last_key is not None and k<=last_key:
                    raise InvalidJcsException(iter)
                last_key=k
            elif c=='{':#object
                self.isValidObject(iter)
                c=next(iter)
            elif c=='[':#array
                self.isValidArray(iter)
                c=next(iter)
            elif c in 'tfn':
                self.isValidLiteral(c, iter)
                c=next(iter)
            elif c=='}':
                if last_c==',':
                    raise InvalidJcsException(iter)
            else:
                c=self.isValidNumber(c,iter)
            if c=='}':
                return
            elif c!=',':
                raise InvalidJcsException(iter)
            last_c=c            
    
    def isValidNumber(self,pre_c:str,iter: CharIterator)->str:
        """
            [+-]?nnn(([eE][+-]?(nnn))(.nnn)))?
        """

        #1文字目
        num=0      
        if pre_c in ['+', '-','.']:
            pass
        elif pre_c.isdigit():
            num+=1
        else:
            raise InvalidJcsException(iter)
        #整数部
        c:str
        if pre_c=='.':
            c=pre_c
        else:
            while True:
                c = next(iter)
                if c.isdigit():
                    num+=1
                    continue
                break
        #少数部
        if c=='.':
            while True:                    
                c = next(iter)
                if c.isdigit():
                    num+=1
                    continue
                break
        if num==0:
            raise InvalidJcsException(iter) #数値がない
        #指数部
        if c in ['e','E']:
            num=0
            c = next(iter)        
            if c in ['+', '-']:
                pass
            elif c.isdigit():
                num+=1
            else:
                raise InvalidJcsException(iter) #指数部が非[数符号]
            #整数部
            while True:
                c = next(iter)
                if c.isdigit():
                    num+=1
                    continue                
                break
        if num==0:
            raise InvalidJcsException(iter) #数値がない
        return c


    
    # 文字列の検証（エスケープ処理対応）
    def isValidString(self, iter: Iterator[str]):
        while True:
            c = next(iter)
            if c == '"':  # 文字列終了
                return
            elif c == '\\':  # エスケープシーケンスの開始
                c = next(iter)  # エスケープされた文字
                if c not in '"\\/bfnrtu':
                    raise InvalidJcsException(iter)                
    def isValidKey(self, iter: Iterator[str])->str:
        k=""
        while True:
            c = next(iter)
            if c == '"':  # 文字列終了
                if len(k)==0:
                    raise InvalidJcsException(iter)
                return k
            elif c == '\\':  # エスケープシーケンスの開始
                k+=c
                c = next(iter)  # エスケープされた文字
                if c not in '"\\/bfnrtu':
                    raise InvalidJcsException(iter)
            k+=c
    def isValidLiteral(self, c: str, iter: CharIterator):
        if c == 't':
            expected = "rue"
        elif c == 'f':
            expected = "alse"
        elif c == 'n':
            expected = "ull"
        else:
            raise InvalidJcsException(iter)

        for expected_char in expected:
            next_c = next(iter)
            if next_c != expected_char:
                raise InvalidJcsException(iter)

# 単体実行時にテスト実行
if __name__ == "__main__":
    S=[
        "[true,false,null]",
        "{\"a\":true,\"b\":false,\"c\":null}",
        "{\"age\":30,\"name\":\"Alice\"}",
        "{\"address\":{\"city\":\"New York\",\"state\":\"NY\"},\"name\":\"Alice\"}",
        "[\"apple\",\"banana\",\"cherry\"]",
        "[{\"age\":30,\"name\":\"Alice\"},{\"age\":25,\"name\":\"Bob\"}]",
        "{}",
        "[]",
        "{\"location\":{\"city\":\"New York\",\"country\":\"USA\"},\"person\":{\"age\":30,\"name\":\"Alice\"}}",
        "[[1,2e3,+3.0],[-4.1e5,5,6],[7,8,9,.5,5.e+1,.5e-3,1.111e0]]",
        # エスケープ系（JCS仕様準拠のエスケープパターン）
        "{\"key\":\"value with \\n newline\"}",
        "{\"key\":\"value with \\t tab\"}",
        "{\"key\":\"value with \\\" quote\"}",
        "{\"key\":\"backslash \\\\\"}",
        "{\"key\":\"unicode \\u0041\"}",  # \u0041 = "A"
        "{\"key\":\"multiple escapes \\\" \\\\ \\n \\t \\b \\f \\r\"}",

        # 数値フォーマット（境界系や端ケース）
        "[0,1,-1,1.0,-1.0,1e10,1E-10,-1.23456789,0.5,.5,5.]",
        "[12345678901234567890]",
        "[1e-1,1e+1,1E-1,1E+1]",

        # ネスト・複合型（複雑構造パターン）
        "{\"a\":{\"b\":[1,2,3],\"c\":true},\"d\":[false,null,3.14]}",
        "[{\"nested\":{\"key\":\"value\"}},[\"array in array\"],{\"k\":1}]"        
    ]
    F=[
        "{\"a\":1,}",        
        "1",
        "true",
        "\"Hello, World!\"",
        "\"Hello \\xWorld\"",
        "\"Hello \\\"World\\\"\"",
        "[true,false,null ]",
        "[\"a\":true,\"b\":false,\"c\":null]",
        "{ }",
        "[ ]",
        "[, ]",
        "{\"z\": 1, \"a\": 2}",
        "[5.+e1]",
        "{\"a\":1,\"a\":2}",
        "{\"person\":{\"name\":\"Alice\",\"age\":30},\"location\":{\"city\":\"New York\",\"country\":\"USA\"}}",
        "[{\"name\":\"Alice\",\"age\":30},{\"name\":\"Bob\",\"age\":25}]",
        "{\"name\":\"Alice\",\"age\":30}",
        "{\"age\":30\"name\":\"Alice\"}",
        "{\"name\":\"Alice\",\"address\":{\"city\":\"New York\",\"state\":\"NY\"}}",
        "\"Hello, World!",
        "{: \"value\"}",
        "{123: \"value\"}",
        "[\"apple\" \"banana\"]",
        "[{\"name\": \"Alice\", \"age\": \"thirty\"}, {\"name\": \"Bob\", \"age\": \"twenty\"}]",
        "{\"name\" \"Alice\"}",
        "[\"apple\", \"banana\",]",
        "{\"name\": \"Alice\", \"age\": 30,}",
        "[{\"name\": \"Alice\"}, \"banana\"]"
        # エスケープ系（不正エスケープ文字含む）
        # "{\"key\":\"value with \\x invalid escape\"}",
        # "{\"key\":\"value with \\u123 invalid unicode\"}",
        # "{\"key\":\"value with \\u12 invalid unicode\"}",
        # "{\"key\":\"value with \\uGGGG invalid unicode\"}",

        # 数値フォーマット（不正な数値表記）
        "[1e]",
        "[1e+]",
        "[1e-]",
        "[.e1]",
        # 余分カンマ・余分スペース
        "[1,2,3,]",
        "{\"a\":1, \"b\":2,}",

        # JCS非対応の単独スカラー（トップレベルスカラー値）
        "true",
        "null",
        "\"string\"",
        "123",

        # キー順序違反（順序逆）
        "{\"b\":1,\"a\":2}"        
    ]



    # テスト実行関数
    def run_tests():
        jcs_validator = JCSValidator()

        print("=== PASS CASES (Expected to pass) ===")
        for i in S:
            try:
                jcs_validator.isJcsToken(CharIterator(i))
                print(f"PASS: {i}")
            except InvalidJcsException as e:
                print(f"FAIL (Unexpected Failure): {i} => {e}")
            except StopIteration:
                print(f"FAIL (Unexpected StopIteration): {i}")

        print("\n=== FAIL CASES (Expected to fail) ===")
        for i in F:
            try:
                jcs_validator.isJcsToken(CharIterator(i))
                print(f"FAIL (Should have failed): {i}")
            except InvalidJcsException as e:
                print(f"PASS (Expected Failure): {i} => {e}")
            except StopIteration:
                print(f"PASS (Expected Failure): {i} => StopIteration")

    run_tests()


