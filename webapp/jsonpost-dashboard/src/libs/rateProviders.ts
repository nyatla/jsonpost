export class LogiticsRateProvider {
    half_x: number;

    constructor(half_x: number) {
        this.half_x = half_x;
    }

    rate(x: number): number {
        return x / (x + this.half_x);
    }
}

export class LogNormalRateProvider {
    sigma: number;
    mu: number;
    pdf0: number;

    constructor(x_max: number, sigma: number) {
        this.sigma = sigma;
        this.mu = Math.log(x_max) + Math.pow(sigma, 2);
        this.pdf0 = this.calculatePdf0();
    }

    private calculatePdf0(): number {
        const x_peak = this.peakPosition();
        return (1 / (x_peak * this.sigma * Math.sqrt(2 * Math.PI))) *
            Math.exp(-Math.pow(Math.log(x_peak) - this.mu, 2) / (2 * Math.pow(this.sigma, 2)));
    }

    private peakPosition(): number {
        return Math.exp(this.mu - Math.pow(this.sigma, 2));
    }

    rate(x: number): number {
        if (x <= 0) return 0;
        const pdf = (1 / (x * this.sigma * Math.sqrt(2 * Math.PI))) *
            Math.exp(-Math.pow(Math.log(x) - this.mu, 2) / (2 * Math.pow(this.sigma, 2)));
        return pdf / this.pdf0;
    }
}

export class TimeLogiticsSizeLnDifficulty {
    g: LogNormalRateProvider;
    l: LogiticsRateProvider;
    params: [number, number, number];

    constructor(time_half_target: number, size_peak_point: number, size_share: number) {
        this.g = new LogNormalRateProvider(size_peak_point, size_share);
        this.l = new LogiticsRateProvider(time_half_target);
        this.params = [time_half_target, size_peak_point, size_share];
    }

    rate(time: number, size: number): number {
        return this.g.rate(size) * this.l.rate(time);
    }

    serialize(): string {
        return `tlsln(${this.params.join(',')})`;
    }
}
