import math
from typing import Optional
from abc import ABC,abstractmethod

class IRateProvider(ABC):
    @abstractmethod
    def rate(x:float):
        ...
class LogiticsRateProvider(IRateProvider):
    """
    ロジスティックライク関数。
    x = half_x のとき f(x) = 0.5 となるように調整する。

    Args:
        x (float): 入力値
        half_x (Optional[float]): 0.5 になる x の値

    Returns:
        float: f(x) の値
    """
    def __init__(self,half_x:Optional[float]):
        self._half_x=half_x
    def rate(self,x: float) -> float:
        return x / (x + self._half_x)
    


class LogNormalRateProvider(IRateProvider):
    def __init__(self, x_max:float, sigma: Optional[float]):
        """
        mu: 対数正規分布の平均
        sigma: 対数正規分布の標準偏差
        """
        self._mu = math.log(x_max) + sigma ** 2  # muをx_maxから逆算
        self._sigma = sigma
        x_peak=self.peak_position()
        self._pdf0=(1 / (x_peak * self._sigma * math.sqrt(2 * math.pi))) * math.exp(-((math.log(x_peak) - self._mu) ** 2) / (2 * self._sigma ** 2))
    
    def rate(self, x: float) -> float:
        """
        対数正規分布のレートを計算します。
        """
        if x <= 0:
            return 0  # x が 0 以下の場合、確率密度は 0 とする
        
        pdf = (1 / (x * self._sigma * math.sqrt(2 * math.pi))) * math.exp(-((math.log(x) - self._mu) ** 2) / (2 * self._sigma ** 2))
        
        return pdf/self._pdf0
    def peak_position(self) -> float:
            """
            対数正規分布のピーク位置を計算します。
            """
            # 微分して得られたピーク位置の計算式
            return math.exp(self._mu - self._sigma ** 2)




class TimeSizeDifficulty:
    """ sizeとtimeの積の二次元Rate。[0,1]
    """
    def __init__(self,time_half_target:int,size_peak_point:int,size_share:float):
        self.g=LogNormalRateProvider(size_peak_point,size_share)
        self.l=LogiticsRateProvider(time_half_target)
    def rate(self,time:int,size:int)->float:
        return self.g.rate(size)*self.l.rate(time)
