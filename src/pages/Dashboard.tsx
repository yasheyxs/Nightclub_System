import { useEffect, useMemo, useState } from "react";
import { KPICard } from "@/components/KPICard";
import {
  DollarSign,
  Calendar,
  TrendingUp,
  Activity,
  Package,
  FileSpreadsheet,
  FileText,
  MoonStar,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { api } from "@/services/api";

interface Metrics {
  eventosMes: number;
  entradasMes: number;
  recaudacionMes: number;
  ocupacionPromedio: number;
}

interface CurrentNight {
  eventName: string;
  fecha: string;
  horaInicio: string;
  horaFinEstimada: string;
  entradasVendidas: number;
  recaudacion: number;
  ocupacion: number;
}

interface UpcomingEvent {
  id: string;
  name: string;
  date: string;
  ocupacion: number;
}

interface PastEvent {
  id: string;
  name: string;
  date: string;
  entradasVendidas: number;
  recaudacion: number;
  ocupacion: number;
}

interface MonthlySummary {
  monthLabel: string;
  totalEventos: number;
  totalEntradas: number;
  recaudacion: number;
  ocupacionPromedio: number;
  mejorNoche: string | null;
}

interface DashboardResponse {
  metrics: Metrics;
  currentNight: CurrentNight | null;
  upcomingEvents: UpcomingEvent[];
  pastEvents: PastEvent[];
  calendarEvents?: PastEvent[];
  monthlySummary: MonthlySummary;
}

const DASHBOARD_ENDPOINT_CANDIDATES = [
  "/dashboard",
  "/dashboard.php",
  "/api/dashboard",
  "/api/dashboard.php",
];

function isDashboardResponse(data: unknown): data is DashboardResponse {
  if (!data || typeof data !== "object") return false;

  const value = data as Record<string, unknown>;

  return (
    typeof value.metrics === "object" &&
    Array.isArray(value.upcomingEvents) &&
    Array.isArray(value.pastEvents) &&
    typeof value.monthlySummary === "object"
  );
}

function buildAbsoluteUrl(endpoint: string): string {
  const baseURL = api.defaults.baseURL ?? "";
  return new URL(endpoint, baseURL || window.location.origin).toString();
}

export default function Dashboard(): JSX.Element {
  const [loading, setLoading] = useState(true);
  const [resolvedEndpoint, setResolvedEndpoint] = useState<string>("");
  const [metrics, setMetrics] = useState<Metrics>({
    eventosMes: 0,
    entradasMes: 0,
    recaudacionMes: 0,
    ocupacionPromedio: 0,
  });
  const [currentNight, setCurrentNight] = useState<CurrentNight | null>(null);
  const [upcomingEvents, setUpcomingEvents] = useState<UpcomingEvent[]>([]);
  const [pastEvents, setPastEvents] = useState<PastEvent[]>([]);
  const [monthlySummary, setMonthlySummary] = useState<MonthlySummary | null>(
    null,
  );

  useEffect(() => {
    const cargarDashboard = async () => {
      try {
        let dashboardData: DashboardResponse | null = null;
        let endpointEncontrado = "";

        for (const endpoint of DASHBOARD_ENDPOINT_CANDIDATES) {
          try {
            const response = await api.get(endpoint, {
              responseType: "text",
              transformResponse: [(raw) => raw],
            });

            const raw =
              typeof response.data === "string"
                ? response.data.trim()
                : String(response.data ?? "").trim();

            if (
              !raw ||
              raw.startsWith("<!doctype") ||
              raw.startsWith("<html")
            ) {
              continue;
            }

            const parsed = JSON.parse(raw);

            if (isDashboardResponse(parsed)) {
              dashboardData = parsed;
              endpointEncontrado = endpoint;
              break;
            }
          } catch {
            // probar siguiente endpoint
          }
        }

        if (!dashboardData) {
          throw new Error(
            "No se encontró un endpoint válido para el dashboard.",
          );
        }

        setResolvedEndpoint(endpointEncontrado);
        setMetrics(dashboardData.metrics);
        setCurrentNight(dashboardData.currentNight);
        setUpcomingEvents(dashboardData.upcomingEvents);
        setPastEvents(dashboardData.pastEvents);
        setMonthlySummary(dashboardData.monthlySummary);
      } catch (err) {
        console.error("Error al cargar dashboard:", err);
      } finally {
        setLoading(false);
      }
    };

    cargarDashboard();
  }, []);

  const exportBaseUrl = useMemo(() => {
    if (!resolvedEndpoint) return "";
    return buildAbsoluteUrl(resolvedEndpoint);
  }, [resolvedEndpoint]);

  const handleExport = (type: "csv" | "pdf") => {
    if (!exportBaseUrl) return;
    const url = new URL(exportBaseUrl);
    url.searchParams.set("export", type);
    window.open(url.toString(), "_blank");
  };

  const formatCurrency = (value: number) =>
    `$${new Intl.NumberFormat("es-AR").format(value)}`;

  return (
    <div className="space-y-8 animate-fade-in">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-4xl font-bold bg-gradient-primary bg-clip-text text-transparent">
            Dashboard Santas
          </h2>
          <p className="text-muted-foreground">
            Seguimiento en tiempo real de eventos, ventas y recaudación
          </p>
        </div>

        <div className="flex gap-3">
          <Button
            variant="outline"
            onClick={() => handleExport("csv")}
            disabled={!exportBaseUrl}
          >
            <FileSpreadsheet className="w-4 h-4 mr-2" />
            Descargar Excel
          </Button>

          <Button onClick={() => handleExport("pdf")} disabled={!exportBaseUrl}>
            <FileText className="w-4 h-4 mr-2" />
            Descargar PDF
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <KPICard
          title="Eventos del Mes"
          value={
            loading
              ? "—"
              : new Intl.NumberFormat("es-AR").format(metrics.eventosMes)
          }
          icon={Calendar}
        />
        <KPICard
          title="Entradas Vendidas"
          value={
            loading
              ? "—"
              : new Intl.NumberFormat("es-AR").format(metrics.entradasMes)
          }
          icon={TrendingUp}
        />
        <KPICard
          title="Recaudación Mensual"
          value={loading ? "—" : formatCurrency(metrics.recaudacionMes)}
          icon={DollarSign}
        />
        <KPICard
          title="Ocupación Promedio"
          value={
            loading
              ? "—"
              : `${new Intl.NumberFormat("es-AR").format(
                  metrics.ocupacionPromedio,
                )}%`
          }
          icon={Activity}
        />
      </div>

      <Card className="bg-gradient-card border-border/50 shadow-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MoonStar className="w-5 h-5 text-primary" />
            Noche en curso
          </CardTitle>
        </CardHeader>
        <CardContent>
          {currentNight ? (
            <div className="grid md:grid-cols-3 gap-6">
              <div>
                <p className="text-sm text-muted-foreground">Evento</p>
                <p className="text-lg font-semibold">
                  {currentNight.eventName}
                </p>
                <p className="text-xs text-muted-foreground">
                  {currentNight.fecha}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Entradas</p>
                <p className="text-lg font-semibold">
                  {new Intl.NumberFormat("es-AR").format(
                    currentNight.entradasVendidas,
                  )}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Recaudación</p>
                <p className="text-lg font-semibold">
                  {formatCurrency(currentNight.recaudacion)}
                </p>
                <Progress value={currentNight.ocupacion} className="h-2 mt-2" />
                <p className="text-xs text-muted-foreground mt-1">
                  Ocupación {currentNight.ocupacion}%
                </p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">
              No hay evento en curso actualmente.
            </p>
          )}
        </CardContent>
      </Card>

      <Card className="border-border shadow-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Calendar className="w-5 h-5 text-primary" />
            Próximos Eventos
          </CardTitle>
        </CardHeader>
        <CardContent>
          {upcomingEvents.length === 0 ? (
            <p className="text-muted-foreground text-sm">
              No hay eventos próximos.
            </p>
          ) : (
            <div className="space-y-3">
              {upcomingEvents.map((e) => (
                <div
                  key={e.id}
                  className="flex justify-between items-center border border-border rounded-lg p-3"
                >
                  <div>
                    <p className="font-semibold">{e.name}</p>
                    <p className="text-sm text-muted-foreground">{e.date}</p>
                  </div>
                  <span className="text-sm font-medium text-primary">
                    {e.ocupacion}%
                  </span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="border-border shadow-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Package className="w-5 h-5 text-primary" />
            Eventos Realizados
          </CardTitle>
        </CardHeader>
        <CardContent>
          {pastEvents.length === 0 ? (
            <p className="text-muted-foreground text-sm">
              No hay eventos pasados.
            </p>
          ) : (
            <div className="space-y-4">
              {pastEvents.map((p) => (
                <div
                  key={p.id}
                  className="border border-border rounded-lg p-3 flex justify-between items-center"
                >
                  <div>
                    <p className="font-semibold">{p.name}</p>
                    <p className="text-sm text-muted-foreground">{p.date}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold">
                      {formatCurrency(p.recaudacion)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {p.entradasVendidas} entradas
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="border-border shadow-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-primary" />
            Resumen Mensual
          </CardTitle>
        </CardHeader>
        <CardContent>
          {monthlySummary ? (
            <div className="grid md:grid-cols-3 gap-6">
              <div>
                <p className="text-sm text-muted-foreground">Mes</p>
                <p className="text-lg font-semibold">
                  {monthlySummary.monthLabel}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Total Eventos</p>
                <p className="text-lg font-semibold">
                  {monthlySummary.totalEventos}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Recaudación</p>
                <p className="text-lg font-semibold">
                  {formatCurrency(monthlySummary.recaudacion)}
                </p>
              </div>
            </div>
          ) : (
            <p className="text-muted-foreground text-sm">
              Sin datos del mes actual.
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
