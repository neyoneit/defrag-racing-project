declare module 'gif.js' {
    export default class GIF {
        constructor(options: {
            workers?: number;
            quality?: number;
            width?: number;
            height?: number;
            workerScript?: string;
        });
        addFrame(canvas: HTMLCanvasElement, options?: { copy?: boolean; delay?: number }): void;
        on(event: string, callback: (blob: Blob) => void): void;
        render(): void;
    }
}
