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
  detalle?: string;
  details?: string;
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
        const { data } = await api.post<ValidarQrResponse>("/validar_qr.php", {
          qr_codigo: codigoNormalizado,
          usuario_validador_id: user?.id ?? null,
          dispositivo: "scanner-web",
        });

        const nextResultado =
          data?.resultado ?? (data?.ok ? "valido" : "invalido");

        setResultado(nextResultado);
        setMensaje(data?.mensaje ?? "Validación procesada.");

        if (typeof data?.entradas_escaneadas === "number") {
          setEntradasEscaneadas(
            Math.max(0, Math.floor(data.entradas_escaneadas)),
          );
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

        setResultado(responseData?.resultado ?? "invalido");
        setMensaje(
          responseData?.mensaje ??
            responseData?.detalle ??
            responseData?.details ??
            responseData?.error ??
            "No se pudo validar el QR.",
        );

        if (typeof responseData?.entradas_escaneadas === "number") {
          setEntradasEscaneadas(
            Math.max(0, Math.floor(responseData.entradas_escaneadas)),
          );
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
    [isProcessing, user?.id],
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
        className="pointer-events-none absolute left-[-9999px] top-[-9999px] opacity-0"
        aria-hidden="true"
        tabIndex={-1}
      />

      <div>
        <h2 className="bg-gradient-primary bg-clip-text text-3xl font-bold text-transparent">
          QR Scanner
        </h2>
        <p className="text-muted-foreground">
          Escaneá con el lector y verás el resultado automáticamente.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6">
        <Card className="border-border/50 bg-gradient-card shadow-card">
          <CardHeader>
            <CardTitle>Resultado de validación</CardTitle>
          </CardHeader>

          <CardContent className="space-y-6">
            <div className="rounded-2xl border border-border/60 p-5 text-center">
              <p className="mb-2 text-sm uppercase tracking-wide text-muted-foreground">
                Entradas escaneadas
              </p>
              <p className="text-primary text-6xl leading-none font-black sm:text-7xl">
                {entradasEscaneadas}
              </p>
            </div>

            <div className="space-y-2 rounded-2xl border border-border/60 p-5 text-center">
              <StatusIcon
                className={`mx-auto h-12 w-12 ${resultadoConfig.className}`}
              />

              <p
                className={`text-4xl font-black sm:text-5xl ${resultadoConfig.className}`}
              >
                {resultadoConfig.label}
              </p>

              <p className="text-sm text-muted-foreground sm:text-base">
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
