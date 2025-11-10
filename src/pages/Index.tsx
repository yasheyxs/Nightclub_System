import { useEffect, useState } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { MetricCard } from "@/components/MetricCard";
import {
  Ticket,
  DollarSign,
  TrendingUp,
  Calendar,
  Package,
  AlertCircle,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

// ========================
// Tipos
// ========================
type Variant = "primary" | "accent" | "success" | "warning" | "default"; // ajustá a los variants reales de tu <MetricCard />

interface LocationMetrics {
  entradas: number | null;
  recaudacion: number | null; // en moneda menor (p.ej. ARS centavos) o entero
  ocupacion: number | null; // porcentaje 0-100
  stock: number | null; // cantidad de alertas
}

type MetricsByLocation = Partial<Record<Location, LocationMetrics>>;

interface EventItem {
  id?: string | number;
  title: string;
  date: string; // ISO o label corto ("15 Nov")
  location: string; // sucursal/nombre
  ocupacion: number; // 0-100
}

type StockStatus = "bajo" | "ok" | "critico";

interface StockItem {
  id?: string | number;
  product: string;
  stock: number;
  min: number;
  status: StockStatus;
}

interface GlobalSummary {
  totalEntradas: number | null;
  totalRecaudacion: number | null; // en moneda menor o entero
  ocupacionPromedio: number | null; // 0-100
}

// (Opcional) Estructura de respuesta del backend para tipar el fetch
interface DashboardResponse {
  metrics: MetricsByLocation;
  events: EventItem[];
  stock: StockItem[];
  globalSummary: GlobalSummary;
}

export default function Index() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");

  const [metrics, setMetrics] = useState<MetricsByLocation>({});
  const [events, setEvents] = useState<EventItem[]>([]);
  const [stockItems, setStockItems] = useState<StockItem[]>([]);
  const [globalSummary, setGlobalSummary] = useState<GlobalSummary>({
    totalEntradas: null,
    totalRecaudacion: null,
    ocupacionPromedio: null,
  });
  const [loading, setLoading] = useState<boolean>(true);

  useEffect(() => {
    // 🔹 Ejemplo: Cargar datos reales del backend
    // (asegurate de que el endpoint devuelva el shape de DashboardResponse)
    // fetch("/api/dashboard")
    //   .then((res) => res.json() as Promise<DashboardResponse>)
    //   .then((data) => {
    //     setMetrics(data.metrics ?? {});
    //     setEvents(Array.isArray(data.events) ? data.events : []);
    //     setStockItems(Array.isArray(data.stock) ? data.stock : []);
    //     setGlobalSummary(
    //       data.globalSummary ?? {
    //         totalEntradas: null,
    //         totalRecaudacion: null,
    //         ocupacionPromedio: null,
    //       }
    //     );
    //   })
    //   .catch((err) => console.error("Error al cargar dashboard:", err))
    //   .finally(() => setLoading(false));

    setLoading(false); // eliminar cuando se integre la API real
  }, []);

  // fallback seguro si todavía no hay métricas para la location seleccionada
  const emptyMetrics: LocationMetrics = {
    entradas: null,
    recaudacion: null,
    ocupacion: null,
    stock: null,
  };
  const currentMetrics: LocationMetrics =
    metrics[selectedLocation] ?? emptyMetrics;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-5xl font-bold bg-gradient-primary bg-clip-text text-transparent mb-2">
            Dashboard
          </h1>
          <p className="text-muted-foreground text-lg">
            Control en tiempo real de todas las operaciones
          </p>
        </div>
        <LocationSelector
          selectedLocation={selectedLocation}
          onLocationChange={setSelectedLocation}
        />
      </div>

      {/* MÉTRICAS PRINCIPALES */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Entradas Vendidas"
          value={
            loading
              ? "—"
              : currentMetrics.entradas !== null
                ? currentMetrics.entradas
                : "—"
          }
          icon={Ticket}
          variant={"primary" as Variant}
        />
        <MetricCard
          title="Recaudación Estimada"
          value={
            loading
              ? "—"
              : currentMetrics.recaudacion !== null
                ? `$${(currentMetrics.recaudacion / 1000).toFixed(1)}K`
                : "—"
          }
          icon={DollarSign}
          variant={"accent" as Variant}
        />
        <MetricCard
          title="Ocupación"
          value={
            loading
              ? "—"
              : currentMetrics.ocupacion !== null
                ? `${currentMetrics.ocupacion}%`
                : "—"
          }
          icon={TrendingUp}
          variant={"success" as Variant}
        />
        <MetricCard
          title="Alertas de Stock"
          value={
            loading
              ? "—"
              : currentMetrics.stock !== null
                ? currentMetrics.stock
                : "—"
          }
          icon={AlertCircle}
          variant={
            currentMetrics.stock && currentMetrics.stock > 0
              ? ("warning" as Variant)
              : ("default" as Variant)
          }
        />
      </div>

      {/* EVENTOS */}
      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="border-border">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5 text-primary" />
              Próximos Eventos
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {loading ? (
              <p className="text-muted-foreground text-sm">Cargando...</p>
            ) : events.length === 0 ? (
              <p className="text-muted-foreground text-sm">
                No hay eventos próximos
              </p>
            ) : (
              events.map((event) => (
                <div
                  key={event.id ?? `${event.title}-${event.date}`}
                  className="flex items-center justify-between p-3 bg-card border border-border rounded-lg"
                >
                  <div>
                    <p className="font-semibold text-foreground">
                      {event.title}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      {event.date} · {event.location}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-bold text-primary">
                      {event.ocupacion}%
                    </p>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        {/* STOCK */}
        <Card className="border-border">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Package className="h-5 w-5 text-warning" />
              Stock Crítico
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {loading ? (
              <p className="text-muted-foreground text-sm">Cargando...</p>
            ) : stockItems.length === 0 ? (
              <p className="text-muted-foreground text-sm">
                No hay alertas de stock
              </p>
            ) : (
              stockItems.map((item) => (
                <div
                  key={item.id ?? item.product}
                  className="flex items-center justify-between p-3 bg-warning/10 border border-warning/30 rounded-lg"
                >
                  <div className="flex-1">
                    <p className="font-semibold text-foreground">
                      {item.product}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      Mínimo: {item.min} unidades
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-bold text-warning">
                      {item.stock}
                    </p>
                    <p className="text-xs uppercase text-warning font-semibold">
                      {item.status === "bajo"
                        ? "Bajo"
                        : item.status === "critico"
                          ? "Crítico"
                          : "OK"}
                    </p>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      {/* RESUMEN GLOBAL */}
      <Card className="border-primary/30 bg-primary/5">
        <CardHeader>
          <CardTitle>Resumen Global - Todas las Sucursales</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-muted-foreground text-sm text-center py-4">
              Cargando datos globales...
            </p>
          ) : (
            <div className="grid gap-4 md:grid-cols-3">
              <div className="text-center p-4">
                <p className="text-sm text-muted-foreground mb-2">
                  Total Entradas Hoy
                </p>
                <p className="text-4xl font-bold text-primary">
                  {globalSummary.totalEntradas ?? "—"}
                </p>
              </div>
              <div className="text-center p-4">
                <p className="text-sm text-muted-foreground mb-2">
                  Recaudación Total
                </p>
                <p className="text-4xl font-bold text-accent">
                  {globalSummary.totalRecaudacion !== null
                    ? `$${(globalSummary.totalRecaudacion / 1000).toFixed(1)}K`
                    : "—"}
                </p>
              </div>
              <div className="text-center p-4">
                <p className="text-sm text-muted-foreground mb-2">
                  Ocupación Promedio
                </p>
                <p className="text-4xl font-bold text-success">
                  {globalSummary.ocupacionPromedio !== null
                    ? `${globalSummary.ocupacionPromedio}%`
                    : "—"}
                </p>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
