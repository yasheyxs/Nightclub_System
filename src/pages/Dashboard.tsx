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
  ScanQrCode,
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
  entradasEscaneadas: number;
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

const DASHBOARD_URL = "http://127.0.0.1:9000/api/dashboard.php";

export default function Dashboard(): JSX.Element {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<DashboardResponse | null>(null);
  const [selectedEvent, setSelectedEvent] = useState<EventData | null>(null);
  const [openSummary, setOpenSummary] = useState(false);
  const [calendarDate, setCalendarDate] = useState<Date | null>(null);
  const [calendarMessage, setCalendarMessage] = useState<string | null>(null);
  const [selectedMonth, setSelectedMonth] = useState(
    new Date().toISOString().slice(0, 7),
  );

  const fetchDashboard = useCallback(async () => {
    setLoading(true);

    try {
      const res = await fetch(`${DASHBOARD_URL}?month=${selectedMonth}`);

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const json: DashboardResponse = await res.json();

      console.log("DASHBOARD OK:", json);

      setData(json);
    } catch (err) {
      console.error("Error al cargar dashboard:", err);
    } finally {
      setLoading(false);
    }
  }, [selectedMonth]);

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
      (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
    );
  }, [data]);

  const calendarEvents = useMemo(() => {
    if (!data) return [];
    return data.calendarEvents?.length ? data.calendarEvents : data.pastEvents;
  }, [data]);

  const maxSelectableMonth = new Date().toISOString().slice(0, 7);

  const handleMonthChange = (event: ChangeEvent<HTMLInputElement>) => {
    if (!event.target.value) return;
    setSelectedMonth(event.target.value);
  };

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
      const event = findEventByDate(date);

      if (!event) {
        setSelectedEvent(null);
        setCalendarMessage("No hay evento registrado este día.");
      } else {
        setCalendarMessage(null);
        setSelectedEvent(event);
      }

      setOpenSummary(true);
    }

    lastClickRef.current = { date, time: now };
  };

  if (loading || !data) {
    return (
      <div className="flex justify-center py-20 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" />
        Cargando dashboard...
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-fade-in">
      {/* HEADER */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-4xl font-bold text-primary">Dashboard Santas</h2>
          <p className="text-muted-foreground">
            Seguimiento de ventas y eventos
          </p>
        </div>

        <div className="flex gap-2">
          <Input
            type="month"
            value={selectedMonth}
            onChange={handleMonthChange}
            max={maxSelectableMonth}
          />

          <Button
            onClick={() =>
              window.open(`${DASHBOARD_URL}?month=${selectedMonth}&export=csv`)
            }
          >
            Excel
          </Button>

          <Button
            onClick={() =>
              window.open(`${DASHBOARD_URL}?month=${selectedMonth}&export=pdf`)
            }
          >
            PDF
          </Button>
        </div>
      </div>

      {/* KPI */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6">
        <KPICard
          title="Eventos"
          value={data.metrics.eventosMes.toString()}
          icon={Calendar}
        />
        <KPICard
          title="Entradas"
          value={data.metrics.entradasMes.toString()}
          icon={TrendingUp}
        />
        <KPICard
          title="Escaneadas"
          value={data.metrics.entradasEscaneadas.toString()}
          icon={ScanQrCode}
        />
        <KPICard
          title="Recaudación"
          value={formatCurrency(data.metrics.recaudacionMes)}
          icon={DollarSign}
        />
        <KPICard
          title="Ocupación"
          value={`${data.metrics.ocupacionPromedio}%`}
          icon={Activity}
        />
      </div>
    </div>
  );
}
