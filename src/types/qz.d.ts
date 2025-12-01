declare module "qz-tray-types" {
  export interface QZWebSocket {
    connect(): Promise<void>;
    isActive(): boolean;
  }

  export interface QZConfig {
    printer: string;
    options?: Record<string, unknown>;
  }

  export interface QZConfigs {
    create(printer: string, options?: Record<string, unknown>): QZConfig;
  }

  export interface QZ {
    websocket: QZWebSocket;
    configs: QZConfigs;
    print(config: QZConfig, data: (string | Uint8Array)[]): Promise<void>;
  }
}

declare global {
  interface Window {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    qz?: any;
  }
}

export {};