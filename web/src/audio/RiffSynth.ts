import { RIFF, RIFF_TEMPO, eighthDuration, midiToFrequency, riffDuration } from './riff'

/**
 * Synthétiseur stoner : guitare fuzz (deux dents de scie désaccordées +
 * saturation + filtre), grosse caisse et caisse claire synthétisées.
 * Aucun fichier audio : tout est généré par la Web Audio API.
 */
export class RiffSynth {
  private ctx: AudioContext | null = null
  private master: GainNode | null = null
  private analyser: AnalyserNode | null = null
  private timer: ReturnType<typeof setInterval> | null = null
  private nextLoopAt = 0

  constructor(
    private readonly createContext: () => AudioContext = () => new AudioContext(),
    private readonly tempo = RIFF_TEMPO,
  ) {}

  get playing(): boolean {
    return this.timer !== null
  }

  /** Niveau sonore instantané (0 → 1), pour animer la cible au rythme */
  level(): number {
    if (!this.analyser) return 0
    const data = new Uint8Array(this.analyser.fftSize)
    this.analyser.getByteTimeDomainData(data)
    let peak = 0
    for (const value of data) peak = Math.max(peak, Math.abs(value - 128))
    return peak / 128
  }

  async start(): Promise<void> {
    if (this.playing) return

    this.ctx ??= this.createContext()
    await this.ctx.resume()

    this.master = this.ctx.createGain()
    this.master.gain.value = 0.35
    this.analyser = this.ctx.createAnalyser()
    this.analyser.fftSize = 512
    this.master.connect(this.analyser).connect(this.ctx.destination)

    // Planification par anticipation : précis même si le thread principal est occupé
    this.nextLoopAt = this.ctx.currentTime + 0.05
    const schedule = () => {
      const ctx = this.ctx
      if (!ctx) return
      while (this.nextLoopAt < ctx.currentTime + 0.5) {
        this.scheduleLoop(this.nextLoopAt)
        this.nextLoopAt += riffDuration(this.tempo)
      }
    }
    schedule()
    this.timer = setInterval(schedule, 100)
  }

  stop(): void {
    if (this.timer) clearInterval(this.timer)
    this.timer = null

    const ctx = this.ctx
    const master = this.master
    if (ctx && master) {
      // Fondu court pour éviter le clic de fin
      master.gain.setTargetAtTime(0, ctx.currentTime, 0.05)
      setTimeout(() => master.disconnect(), 300)
    }
    this.master = null
    this.analyser = null
  }

  async dispose(): Promise<void> {
    this.stop()
    await this.ctx?.close()
    this.ctx = null
  }

  private scheduleLoop(at: number): void {
    const eighth = eighthDuration(this.tempo)
    let time = at

    for (const step of RIFF) {
      if (step.note !== null) this.guitar(midiToFrequency(step.note), time, step.eighths * eighth * 0.95)
      time += step.eighths * eighth
    }

    // Batterie : grosse caisse sur les temps 1 et 3, caisse claire sur 2 et 4
    const beats = Math.round(riffDuration(this.tempo) / (eighth * 2))
    for (let beat = 0; beat < beats; beat++) {
      const t = at + beat * eighth * 2
      if (beat % 2 === 0) this.kick(t)
      else this.snare(t)
    }
  }

  private guitar(frequency: number, at: number, duration: number): void {
    const ctx = this.ctx
    const master = this.master
    if (!ctx || !master) return

    const fuzz = ctx.createWaveShaper()
    fuzz.curve = RiffSynth.fuzzCurve()
    fuzz.oversample = '4x'

    const tone = ctx.createBiquadFilter()
    tone.type = 'lowpass'
    tone.frequency.value = 1800
    tone.Q.value = 0.8

    const amp = ctx.createGain()
    amp.gain.setValueAtTime(0, at)
    amp.gain.linearRampToValueAtTime(0.5, at + 0.01)
    amp.gain.exponentialRampToValueAtTime(0.001, at + duration)

    fuzz.connect(tone).connect(amp).connect(master)

    // Deux oscillateurs légèrement désaccordés + l'octave grave : son « mur de fuzz »
    for (const [ratio, detune] of [[1, -7], [1, 7], [0.5, 0]] as const) {
      const osc = ctx.createOscillator()
      osc.type = 'sawtooth'
      osc.frequency.value = frequency * ratio
      osc.detune.value = detune
      osc.connect(fuzz)
      osc.start(at)
      osc.stop(at + duration + 0.02)
    }
  }

  private kick(at: number): void {
    const ctx = this.ctx
    const master = this.master
    if (!ctx || !master) return

    const osc = ctx.createOscillator()
    const amp = ctx.createGain()
    osc.frequency.setValueAtTime(140, at)
    osc.frequency.exponentialRampToValueAtTime(42, at + 0.12)
    amp.gain.setValueAtTime(0.9, at)
    amp.gain.exponentialRampToValueAtTime(0.001, at + 0.3)
    osc.connect(amp).connect(master)
    osc.start(at)
    osc.stop(at + 0.32)
  }

  private snare(at: number): void {
    const ctx = this.ctx
    const master = this.master
    if (!ctx || !master) return

    const length = Math.floor(ctx.sampleRate * 0.2)
    const buffer = ctx.createBuffer(1, length, ctx.sampleRate)
    const data = buffer.getChannelData(0)
    for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1

    const noise = ctx.createBufferSource()
    noise.buffer = buffer
    const filter = ctx.createBiquadFilter()
    filter.type = 'highpass'
    filter.frequency.value = 1500
    const amp = ctx.createGain()
    amp.gain.setValueAtTime(0.4, at)
    amp.gain.exponentialRampToValueAtTime(0.001, at + 0.18)
    noise.connect(filter).connect(amp).connect(master)
    noise.start(at)
  }

  private static curve: Float32Array<ArrayBuffer> | null = null

  /** Saturation douce (type « fuzz » à transistors) */
  private static fuzzCurve(): Float32Array<ArrayBuffer> {
    if (RiffSynth.curve) return RiffSynth.curve
    const samples = 1024
    const curve = new Float32Array(new ArrayBuffer(samples * 4))
    const drive = 40
    for (let i = 0; i < samples; i++) {
      const x = (i * 2) / samples - 1
      curve[i] = ((1 + drive) * x) / (1 + drive * Math.abs(x))
    }
    RiffSynth.curve = curve
    return curve
  }
}
