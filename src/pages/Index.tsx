import {
  ChangeEvent,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { KPICard } from "@/components/KPICard";
import {
  DollarSign,
  Calendar,
  TrendingUp,
  Activity,
  FileSpreadsheet,
  FileText,
  MoonStar,
  Loader2,
  BarChart3,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Progress } from "@/components/ui/progress";
import CalendarView from "react-calendar";
import "react-calendar/dist/Calendar.css";

interface Metrics {
  eventosMes: number;
  entradasMes: number;
  recaudacionMes: number;
  ocupacionPromedio: number;
}

interface EventData {
  id: string;
  name: string;
  date: string;
  entradasVendidas: number;
  recaudacion: number;
  ocupacion: number;
  consumoPromedio: number;
  barrasActivas: number;
  mesasReservadas: number;
  activo?: boolean;
  cerrado?: boolean;
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
  upcomingEvents: EventData[];
  pastEvents: EventData[];
  calendarEvents?: EventData[];
  monthlySummary: MonthlySummary;
}

export default function Dashboard(): JSX.Element {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<DashboardResponse | null>(null);
  const [selectedEvent, setSelectedEvent] = useState<EventData | null>(null);
  const [openSummary, setOpenSummary] = useState(false);
  const [calendarDate, setCalendarDate] = useState<Date | null>(null);
  const [calendarMessage, setCalendarMessage] = useState<string | null>(null);
  const [selectedMonth, setSelectedMonth] = useState(
    new Date().toISOString().slice(0, 7)
  );

  const fetchDashboard = useCallback(
    async (extraParams?: Record<string, string>) => {
      setLoading(true);
      try {
        const params = new URLSearchParams({ month: selectedMonth });
        if (extraParams) {
          Object.entries(extraParams).forEach(([key, value]) => {
            params.set(key, value);
          });
        }
        const res = await fetch(
          `http://localhost:8000/api/dashboard.php?${params.toString()}`
        );
        const json = (await res.json()) as DashboardResponse;
        setData(json);
      } catch (err) {
        console.error("Error al cargar dashboard:", err);
      } finally {
        setLoading(false);
      }
    },
    [selectedMonth]
  );

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  useEffect(() => {
    const [year, month] = selectedMonth
      .split("-")
      .map((value) => parseInt(value, 10));
    if (!Number.isNaN(year) && !Number.isNaN(month)) {
      setCalendarDate(new Date(year, month - 1, 1));
    }
  }, [selectedMonth]);

  const formatCurrency = (v: number) =>
    `$${new Intl.NumberFormat("es-AR").format(v)}`;
  const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString("es-AR", {
      weekday: "short",
      day: "numeric",
      month: "short",
    });

  const pastEvents = useMemo(() => {
    if (!data) return [];
    return [...data.pastEvents].sort(
      (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime()
    );
  }, [data]);

  const calendarEvents = useMemo(() => {
    if (!data) return [];
    if (data.calendarEvents && data.calendarEvents.length > 0) {
      return data.calendarEvents;
    }
    return data.pastEvents;
  }, [data]);

  const maxSelectableMonth = useMemo(
    () => new Date().toISOString().slice(0, 7),
    []
  );

  const handleMonthChange = (event: ChangeEvent<HTMLInputElement>) => {
    if (!event.target.value) return;
    setSelectedMonth(event.target.value);
  };

  // ---- Calendario filtrable ----
  const findEventByDate = (date: Date) => {
    if (!calendarEvents.length) return null;
    const target = date.toISOString().split("T")[0];
    return calendarEvents.find((e) => e.date.split("T")[0] === target) || null;
  };

  const lastClickRef = useRef<{ date: Date; time: number } | null>(null);
  const handleDayClick = (date: Date) => {
    const now = Date.now();
    const last = lastClickRef.current;
    const sameDay = last && last.date.toDateString() === date.toDateString();
    const isDouble = sameDay && now - (last?.time ?? 0) < 400;

    if (isDouble) {
      setCalendarDate(date);
      const event = findEventByDate(date);
      if (!event) {
        setSelectedEvent(null);
        setCalendarMessage("No hay evento registrado este día.");
        setOpenSummary(true);
      } else {
        setCalendarMessage(null);
        setSelectedEvent(event);
        setOpenSummary(true);
      }
    }
    lastClickRef.current = { date, time: now };
  };

  if (loading || !data) {
    return (
      <div className="flex justify-center py-20 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" /> Cargando dashboard...
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-4xl font-bold bg-gradient-primary bg-clip-text text-transparent">
            Dashboard Santas
          </h2>
          <p className="text-muted-foreground">
            Seguimiento de ventas, eventos y ocupación
          </p>
        </div>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <Input
            type="month"
            value={selectedMonth}
            onChange={handleMonthChange}
            className="w-full sm:w-56"
            max={maxSelectableMonth}
          />
          <Button
            variant="outline"
            onClick={() =>
              window.open(
                `http://localhost:8000/api/dashboard.php?month=${selectedMonth}&export=csv`
              )
            }
          >
            <FileSpreadsheet className="w-4 h-4 mr-2" /> Excel
          </Button>
          <Button
            onClick={() =>
              window.open(
                `http://localhost:8000/api/dashboard.php?month=${selectedMonth}&export=pdf`
              )
            }
          >
            <FileText className="w-4 h-4 mr-2" /> PDF
          </Button>
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <KPICard
          title="Eventos del Mes"
          value={data.metrics.eventosMes.toString()}
          icon={Calendar}
        />
        <KPICard
          title="Entradas Vendidas"
          value={data.metrics.entradasMes.toLocaleString("es-AR")}
          icon={TrendingUp}
        />
        <KPICard
          title="Recaudación Mensual"
          value={formatCurrency(data.metrics.recaudacionMes)}
          icon={DollarSign}
        />
        <KPICard
          title="Ocupación Promedio"
          value={`${data.metrics.ocupacionPromedio}%`}
          icon={Activity}
        />
      </div>

      {/* Noche en curso */}
      <Card className="bg-gradient-card border-border/50 shadow-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MoonStar className="w-5 h-5 text-primary" /> Noche en curso
          </CardTitle>
        </CardHeader>
        <CardContent>
          {data.currentNight ? (
            <div className="grid md:grid-cols-3 gap-6">
              <div>
                <p className="text-sm text-muted-foreground">
                  {data.currentNight.fecha}
                </p>
                <p className="text-xl font-semibold">
                  {data.currentNight.eventName}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Entradas</p>
                <p className="text-lg font-semibold">
                  {data.currentNight.entradasVendidas}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Ocupación</p>
                <Progress value={data.currentNight.ocupacion} className="h-2" />
                <p className="text-xs mt-1 text-muted-foreground">
                  {data.currentNight.ocupacion}% de capacidad
                </p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">
              No hay evento activo en este momento.
            </p>
          )}
        </CardContent>
      </Card>

      {/* Calendario de eventos */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Calendar className="h-5 w-5 text-primary" />
            Calendario de eventos
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border border-border bg-neutral-900 text-neutral-100 p-3 shadow-sm select-none">
            <CalendarView
              className="w-full dark-calendar"
              locale="es-AR"
              value={calendarDate || new Date()}
              onClickDay={handleDayClick}
              tileClassName={({ date }) => {
                const e = findEventByDate(date);
                if (!e) return "";
                if (e.cerrado) return "event-purple";
                const eventDate = new Date(e.date);
                return eventDate.getTime() >= Date.now()
                  ? "event-green"
                  : "event-blue";
              }}
              tileContent={({ date }) => {
                const e = findEventByDate(date);
                return e ? (
                  <div
                    className={`mt-1 h-1 w-1 mx-auto rounded-full ${
                      e.cerrado
                        ? "bg-purple-400"
                        : new Date(e.date).getTime() >= Date.now()
                        ? "bg-green-400"
                        : "bg-blue-400"
                    }`}
                  />
                ) : null;
              }}
            />
          </div>
          <p className="text-xs text-muted-foreground mt-2">
            Doble clic sobre un día para ver si hay evento registrado o sus
            resultados.
          </p>
        </CardContent>
      </Card>

      {/* Listado de eventos */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-primary" /> Eventos y resultados
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {pastEvents.length === 0 && (
            <p className="text-sm text-muted-foreground">
              No hay eventos registrados para el mes seleccionado.
            </p>
          )}
          {pastEvents.map((e) => (
            <div
              key={e.id}
              className="flex justify-between items-center border border-border rounded-lg p-3 hover:bg-surface/10 transition-all"
            >
              <div>
                <p className="font-semibold">{e.name}</p>
                <p className="text-sm text-muted-foreground">
                  {formatDate(e.date)}
                </p>
              </div>
              <div className="flex items-center gap-4">
                <span className="text-sm text-muted-foreground">
                  {formatCurrency(e.recaudacion)}
                </span>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    setCalendarMessage(null);
                    setSelectedEvent(e);
                    setOpenSummary(true);
                  }}
                >
                  Ver resumen
                </Button>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      {/* Modal resumen */}
      <Dialog
        open={openSummary}
        onOpenChange={(open) => {
          setOpenSummary(open);
          if (!open) {
            setCalendarMessage(null);
            setSelectedEvent(null);
          }
        }}
      >
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {selectedEvent
                ? selectedEvent.name
                : calendarMessage || "Resumen del evento"}
            </DialogTitle>
          </DialogHeader>
          {calendarMessage && (
            <p className="text-sm text-muted-foreground">{calendarMessage}</p>
          )}
          {selectedEvent && !calendarMessage && (
            <div className="space-y-3">
              <div className="flex items-center justify-between text-sm text-muted-foreground">
                <p>Fecha: {formatDate(selectedEvent.date)}</p>
                <span
                  className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                    selectedEvent.cerrado
                      ? "bg-purple-100/10 text-purple-300"
                      : new Date(selectedEvent.date).getTime() >= Date.now()
                      ? "bg-green-100/10 text-green-300"
                      : "bg-blue-100/10 text-blue-300"
                  }`}
                >
                  {selectedEvent.cerrado
                    ? "Evento cerrado"
                    : new Date(selectedEvent.date).getTime() >= Date.now()
                    ? "Próximo evento"
                    : "Evento registrado"}
                </span>
              </div>
              <p>Entradas: {selectedEvent.entradasVendidas}</p>
              <p>Recaudación: {formatCurrency(selectedEvent.recaudacion)}</p>
              <Progress value={selectedEvent.ocupacion} className="h-2" />
              <p className="text-xs text-muted-foreground">
                Ocupación {selectedEvent.ocupacion}%
              </p>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Estilo calendario */}
      <style
        dangerouslySetInnerHTML={{
          __html: `
          .dark-calendar {
            background-color: #111827;
            color: #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.95rem;
          }
          .dark-calendar .react-calendar__navigation {
            background: transparent !important;
          }
          .dark-calendar .react-calendar__navigation button {
            color: #f3f4f6 !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease, color 0.2s ease;
          }
          .dark-calendar .react-calendar__navigation button:enabled:hover {
            background-color: rgba(59, 130, 246, 0.15) !important;
            color: #93c5fd !important;
          }
          .dark-calendar .react-calendar__navigation button:enabled:active {
            background-color: rgba(59, 130, 246, 0.25) !important;
            color: #bfdbfe !important;
          }
          .dark-calendar .react-calendar__tile {
            padding: 0.8rem 0;
            border-radius: 0.6rem;
            transition: all 0.2s ease;
            background: transparent !important;
            position: relative;
          }
          .dark-calendar .react-calendar__tile:hover {
            background-color: rgba(59, 130, 246, 0.15) !important;
          }
          .dark-calendar .react-calendar__tile--now {
          background-color: rgba(147, 51, 234, 0.15) !important;
            border-radius: 9999px;
          }
          .dark-calendar button:focus {
            background: transparent !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
          }
          .dark-calendar .event-blue {
            background-color: rgba(37, 99, 235, 0.4) !important;
            color: #fff !important;
            border-radius: 0.5rem;
          }
          .dark-calendar .event-blue:hover {
            background-color: rgba(59, 130, 246, 0.5) !important;
          }
          .dark-calendar .event-purple {
            background-color: rgba(126, 34, 206, 0.4) !important;
            color: #fff !important;
            border-radius: 0.5rem;
          }
          .dark-calendar .event-purple:hover {
            background-color: rgba(147, 51, 234, 0.55) !important;
          }
          .dark-calendar .event-green {
            background-color: rgba(34, 197, 94, 0.4) !important;
            color: #fff !important;
            border-radius: 0.5rem;
          }
          .dark-calendar .event-green:hover {
            background-color: rgba(74, 222, 128, 0.55) !important;
          }
          .dark-calendar .react-calendar__month-view__days__day--neighboringMonth {
            opacity: 0.25;
          }
        `,
        }}
      />
    </div>
  );
}
