import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { CheckCircle2, QrCode, TriangleAlert, XCircle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { api } from "@/services/api";
import { useAuth } from "@/hooks/useAuth";

type ResultadoQr = "valido" | "invalido" | "usado" | "anulado";

interface ValidarQrResponse {
  ok?: boolean;
  resultado?: ResultadoQr;
  mensaje?: string;
  entradas_escaneadas?: number;
  data?: {
    evento_id?: number | null;
  };
  error?: string;
  details?: string;
}

interface VentaEntradasResponse {
  eventos?: Array<{
    id: number;
  }>;
}

const QR_COUNTERS_STORAGE_KEY = "qr_scanner_counts_by_event";

function readQrCounters(): Record<string, number> {
  try {
    const raw = localStorage.getItem(QR_COUNTERS_STORAGE_KEY);
    if (!raw) return {};

    const parsed = JSON.parse(raw);
    if (typeof parsed !== "object" || parsed === null) return {};

    return Object.entries(parsed).reduce<Record<string, number>>(
      (acc, [key, value]) => {
        if (typeof value === "number" && Number.isFinite(value) && value >= 0) {
          acc[key] = Math.floor(value);
        }
        return acc;
      },
      {},
    );
  } catch {
    return {};
  }
}

function saveQrCounters(counters: Record<string, number>) {
  localStorage.setItem(QR_COUNTERS_STORAGE_KEY, JSON.stringify(counters));
}

function getResultadoConfig(resultado: ResultadoQr | null) {
  switch (resultado) {
    case "valido":
      return {
        label: "VÁLIDA",
        className: "text-emerald-500",
        icon: CheckCircle2,
      };
    case "usado":
      return {
        label: "USADA",
        className: "text-red-500",
        icon: XCircle,
      };
    case "anulado":
      return {
        label: "ANULADA",
        className: "text-red-500",
        icon: XCircle,
      };
    case "invalido":
      return {
        label: "INVÁLIDA",
        className: "text-red-500",
        icon: TriangleAlert,
      };
    default:
      return {
        label: "ESCANEÁ UN QR",
        className: "text-muted-foreground",
        icon: QrCode,
      };
  }
}

export default function QrScanner() {
  const { user } = useAuth();

  const [resultado, setResultado] = useState<ResultadoQr | null>(null);
  const [mensaje, setMensaje] = useState<string>("Esperando escaneo...");
  const [entradasEscaneadas, setEntradasEscaneadas] = useState(0);
  const [activeEventId, setActiveEventId] = useState<number | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);

  const hiddenInputRef = useRef<HTMLInputElement | null>(null);
  const lastScanRef = useRef<{ value: string; at: number } | null>(null);

  const resultadoConfig = useMemo(
    () => getResultadoConfig(resultado),
    [resultado],
  );

  const focusHiddenInput = useCallback(() => {
    hiddenInputRef.current?.focus();
  }, []);

  useEffect(() => {
    focusHiddenInput();

    const handleWindowClick = () => {
      focusHiddenInput();
    };

    window.addEventListener("click", handleWindowClick);
    return () => {
      window.removeEventListener("click", handleWindowClick);
    };
  }, [focusHiddenInput]);

  useEffect(() => {
    let isMounted = true;

    const loadCounterFromActiveEvent = async () => {
      try {
        const { data } =
          await api.get<VentaEntradasResponse>("/venta_entradas");
        const nextActiveEventId =
          Array.isArray(data?.eventos) && data.eventos.length > 0
            ? Number(data.eventos[0].id)
            : null;

        if (!isMounted) return;

        setActiveEventId(nextActiveEventId);

        if (nextActiveEventId === null) {
          setEntradasEscaneadas(0);
          return;
        }

        const counters = readQrCounters();
        setEntradasEscaneadas(counters[String(nextActiveEventId)] ?? 0);
      } catch (error) {
        console.error("Error al cargar evento activo para scanner:", error);
      }
    };

    void loadCounterFromActiveEvent();

    return () => {
      isMounted = false;
    };
  }, []);

  const updateStoredCounter = useCallback(
    (eventId: number | null, nextValue: number) => {
      if (eventId === null) {
        setEntradasEscaneadas(Math.max(0, Math.floor(nextValue)));
        return;
      }

      const counters = readQrCounters();
      counters[String(eventId)] = Math.max(0, Math.floor(nextValue));
      saveQrCounters(counters);
      setEntradasEscaneadas(counters[String(eventId)]);
    },
    [],
  );

  const validarQr = useCallback(
    async (qrCodigo: string) => {
      const codigoNormalizado = qrCodigo.trim();

      if (!codigoNormalizado || isProcessing) return;

      const ahora = Date.now();
      const lastScan = lastScanRef.current;

      if (
        lastScan &&
        lastScan.value === codigoNormalizado &&
        ahora - lastScan.at < 1200
      ) {
        return;
      }

      setIsProcessing(true);
      lastScanRef.current = { value: codigoNormalizado, at: ahora };

      try {
        const { data } = await api.post("/validar_qr.php", {
          qr_codigo: codigoNormalizado,
          usuario_validador_id: user?.id ?? null,
          dispositivo: "scanner-web",
        });

        const nextResultado =
          data?.resultado ?? (data?.ok ? "valido" : "invalido");
        const eventIdFromResponse =
          typeof data?.data?.evento_id === "number"
            ? data.data.evento_id
            : null;
        const counterEventId = eventIdFromResponse ?? activeEventId;

        setResultado(nextResultado);
        setMensaje(data?.mensaje ?? "Validación procesada.");

        if (typeof data?.entradas_escaneadas === "number") {
          updateStoredCounter(counterEventId, data.entradas_escaneadas);
        } else if (nextResultado === "valido") {
          const counters = readQrCounters();
          const previousCount =
            counterEventId !== null
              ? (counters[String(counterEventId)] ?? 0)
              : entradasEscaneadas;
          updateStoredCounter(counterEventId, previousCount + 1);
        }

        if (
          eventIdFromResponse !== null &&
          eventIdFromResponse !== activeEventId
        ) {
          setActiveEventId(eventIdFromResponse);
        }
      } catch (error: unknown) {
        const responseData =
          typeof error === "object" &&
          error !== null &&
          "response" in error &&
          typeof (error as { response?: unknown }).response === "object"
            ? ((
                error as {
                  response?: { data?: ValidarQrResponse };
                }
              ).response?.data ?? null)
            : null;

        console.error("VALIDAR_QR_ERROR:", responseData);

        const nextResultado = responseData?.resultado ?? "invalido";

        setResultado(nextResultado);
        setMensaje(
          responseData?.mensaje ??
            responseData?.details ??
            responseData?.error ??
            "No se pudo validar el QR.",
        );

        if (typeof responseData?.entradas_escaneadas === "number") {
          updateStoredCounter(activeEventId, responseData.entradas_escaneadas);
        }
      } finally {
        setIsProcessing(false);
        window.setTimeout(() => {
          if (hiddenInputRef.current) {
            hiddenInputRef.current.value = "";
            hiddenInputRef.current.focus();
          }
        }, 50);
      }
    },
    [
      activeEventId,
      entradasEscaneadas,
      isProcessing,
      updateStoredCounter,
      user?.id,
    ],
  );

  const handleScannerSubmit = useCallback(async () => {
    const value = hiddenInputRef.current?.value ?? "";
    await validarQr(value);
  }, [validarQr]);

  const StatusIcon = resultadoConfig.icon;

  return (
    <div className="space-y-6 animate-fade-in" onClick={focusHiddenInput}>
      <input
        ref={hiddenInputRef}
        type="text"
        autoFocus
        autoComplete="off"
        spellCheck={false}
        inputMode="none"
        onBlur={() => window.setTimeout(focusHiddenInput, 10)}
        onKeyDown={(event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            void handleScannerSubmit();
          }
        }}
        className="absolute left-[-9999px] top-[-9999px] opacity-0 pointer-events-none"
        aria-hidden="true"
        tabIndex={-1}
      />

      <div>
        <h2 className="text-3xl font-bold bg-gradient-primary bg-clip-text text-transparent">
          QR Scanner
        </h2>
        <p className="text-muted-foreground">
          Escaneá con el lector y verás el resultado automáticamente.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6">
        <Card className="bg-gradient-card border-border/50 shadow-card">
          <CardHeader>
            <CardTitle>Resultado de validación</CardTitle>
          </CardHeader>

          <CardContent className="space-y-6">
            <div className="rounded-2xl border border-border/60 p-5 text-center">
              <p className="mb-2 text-sm uppercase tracking-wide text-muted-foreground">
                Entradas escaneadas
              </p>
              <p className="text-6xl sm:text-7xl font-black text-primary leading-none">
                {entradasEscaneadas}
              </p>
            </div>

            <div className="rounded-2xl border border-border/60 p-5 text-center space-y-2">
              <StatusIcon
                className={`mx-auto h-12 w-12 ${resultadoConfig.className}`}
              />

              <p
                className={`text-4xl sm:text-5xl font-black ${resultadoConfig.className}`}
              >
                {resultadoConfig.label}
              </p>

              <p className="text-sm sm:text-base text-muted-foreground">
                {mensaje}
              </p>

              {isProcessing && (
                <p className="text-xs text-muted-foreground">
                  Validando escaneo...
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
