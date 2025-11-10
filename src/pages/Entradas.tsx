import React, { useEffect, useState, useMemo } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { Ticket, Users, Crown, Calendar, CheckCircle } from "lucide-react";
import { StatCounter } from "@/components/StatCounter";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card } from "@/components/ui/card";

// ========================
// Tipos
// ========================
interface EntryCounters {
  reservadas: number;
  vendidas: number;
  vip: number;
}

type CountersByLocation = Partial<Record<Location, EntryCounters>>;

interface CapacityByLocation {
  [key: string]: number;
}

interface EntradasResponse {
  counters: CountersByLocation;
  capacity: CapacityByLocation;
}

interface EventOption {
  id: string;
  name: string;
  capacity: number;
  location: Location;
}

// ⚠️ Debe coincidir con StatCounterProps.variant
type StatCounterVariant = "primary" | "accent" | "success" | "default";

export default function Entradas() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");
  const [selectedEvent, setSelectedEvent] = useState<string>("");
  const [events, setEvents] = useState<EventOption[]>([]);
  const [counters, setCounters] = useState<CountersByLocation>({});
  const [capacity, setCapacity] = useState<CapacityByLocation>({});
  const [loading, setLoading] = useState<boolean>(true);
  const [showSummary, setShowSummary] = useState<boolean>(false);

  const formatter = useMemo(
    () => new Intl.NumberFormat("es-AR", { maximumFractionDigits: 0 }),
    []
  );

  // ========================
  // Cargar datos simulados o reales
  // ========================
  useEffect(() => {
    const simulatedEvents: EventOption[] = [
      {
        id: "1",
        name: "Noche de DJ Internacional",
        capacity: 300,
        location: "santas",
      },
      {
        id: "2",
        name: "Pool Party Sunset",
        capacity: 200,
        location: "outdoor",
      },
      { id: "3", name: "Cumpleaños Temático", capacity: 80, location: "kiddo" },
    ];

    setEvents(simulatedEvents);
    setCapacity({
      santas: 300,
      outdoor: 200,
      kiddo: 80,
    });

    setLoading(false);
  }, []);

  const currentCounts: EntryCounters = counters[selectedLocation] ?? {
    reservadas: 0,
    vendidas: 0,
    vip: 0,
  };

  // Capacidad según evento seleccionado o fallback por sucursal
  const selectedEventData = events.find((e) => e.id === selectedEvent);
  const maxCapacity =
    selectedEventData?.capacity ?? capacity[selectedLocation] ?? 0;

  const totalEntradas =
    (currentCounts.reservadas ?? 0) +
    (currentCounts.vendidas ?? 0) +
    (currentCounts.vip ?? 0);

  const handleIncrement = (type: keyof EntryCounters) => {
    setCounters((prev) => ({
      ...prev,
      [selectedLocation]: {
        ...prev[selectedLocation],
        [type]: (prev[selectedLocation]?.[type] ?? 0) + 1,
      },
    }));
  };

  const handleDecrement = (type: keyof EntryCounters) => {
    setCounters((prev) => ({
      ...prev,
      [selectedLocation]: {
        ...prev[selectedLocation],
        [type]: Math.max(0, (prev[selectedLocation]?.[type] ?? 0) - 1),
      },
    }));
  };

  const handleCloseEvent = () => {
    setShowSummary(true);
  };

  const handleConfirmClose = () => {
    console.log("Evento cerrado:", {
      evento: selectedEventData?.name,
      location: selectedLocation,
      resumen: currentCounts,
      total: totalEntradas,
    });
    setShowSummary(false);
  };

  return (
    <div className="space-y-6 animate-fade-in">
      {/* HEADER */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Control de Entradas
          </h1>
          <p className="text-muted-foreground">
            Gestión en tiempo real de ventas por evento
          </p>
        </div>
        <LocationSelector
          selectedLocation={selectedLocation}
          onLocationChange={setSelectedLocation}
        />
      </div>

      {/* SELECCIÓN DE EVENTO */}
      <div className="flex flex-col sm:flex-row items-center gap-4">
        <div className="flex items-center gap-2">
          <Calendar className="text-primary" />
          <span className="font-medium text-foreground">Evento:</span>
        </div>
        <Select
          value={selectedEvent}
          onValueChange={(value) => setSelectedEvent(value)}
        >
          <SelectTrigger className="w-[260px]">
            <SelectValue placeholder="Seleccionar evento" />
          </SelectTrigger>
          <SelectContent>
            {events
              .filter((e) => e.location === selectedLocation)
              .map((event) => (
                <SelectItem key={event.id} value={event.id}>
                  {event.name}
                </SelectItem>
              ))}
          </SelectContent>
        </Select>
      </div>

      {/* Venta de entradas */}
      <div className="bg-card border border-border rounded-lg p-6 mb-6">
        {loading ? (
          <p className="text-muted-foreground text-sm">Cargando...</p>
        ) : selectedEvent ? (
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-muted-foreground uppercase tracking-wide">
                Ventas Totales
              </p>
              <p className="text-5xl font-bold text-primary">
                {formatter.format(totalEntradas)}
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-muted-foreground">Capacidad Máxima</p>
              <p className="text-2xl font-semibold text-foreground">
                {maxCapacity > 0 ? formatter.format(maxCapacity) : "—"}
              </p>
              <div className="mt-2">
                <div className="w-48 h-3 bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-gradient-primary transition-all duration-500"
                    style={{
                      width: maxCapacity
                        ? `${Math.min(
                            (totalEntradas / maxCapacity) * 100,
                            100
                          )}%`
                        : "0%",
                    }}
                  />
                </div>
              </div>
            </div>
          </div>
        ) : (
          <p className="text-muted-foreground text-sm italic">
            Seleccione un evento para comenzar
          </p>
        )}
      </div>

      {/* CONTADORES */}
      {selectedEvent && (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <MemoCounter
            title="Entradas Reservadas"
            count={currentCounts.reservadas}
            onIncrement={() => handleIncrement("reservadas")}
            onDecrement={() => handleDecrement("reservadas")}
            variant="primary"
            maxCount={maxCapacity}
          />
          <MemoCounter
            title="Entradas Vendidas"
            count={currentCounts.vendidas}
            onIncrement={() => handleIncrement("vendidas")}
            onDecrement={() => handleDecrement("vendidas")}
            variant="accent"
            maxCount={maxCapacity}
          />
          <MemoCounter
            title="Entradas VIP"
            count={currentCounts.vip}
            onIncrement={() => handleIncrement("vip")}
            onDecrement={() => handleDecrement("vip")}
            variant="success"
            maxCount={Math.floor(maxCapacity * 0.2)}
          />
        </div>
      )}

      {/* RECUADROS RESUMEN (abajo de los contadores) */}
      {selectedEvent && (
        <div className="grid gap-4 md:grid-cols-3 mt-8">
          <ResumenBox
            icon={Ticket}
            title="Reservadas"
            value={currentCounts.reservadas}
          />
          <ResumenBox
            icon={Users}
            title="Vendidas"
            value={currentCounts.vendidas}
          />
          <ResumenBox icon={Crown} title="VIP" value={currentCounts.vip} />
        </div>
      )}

      {/* BOTÓN CERRAR EVENTO */}
      {selectedEvent && (
        <div className="flex justify-end mt-6">
          <Button
            onClick={handleCloseEvent}
            disabled={!selectedEvent}
            className="bg-success hover:bg-success/80 text-white"
          >
            <CheckCircle className="mr-2 h-5 w-5" />
            Cerrar Evento
          </Button>
        </div>
      )}

      {/* DIALOGO DE RESUMEN */}
      <Dialog open={showSummary} onOpenChange={setShowSummary}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Resumen del Evento</DialogTitle>
          </DialogHeader>

          <div className="space-y-3 mt-4">
            <p className="text-muted-foreground">
              <strong>Evento:</strong> {selectedEventData?.name}
            </p>
            <p className="text-muted-foreground">
              <strong>Ubicación:</strong> {selectedLocation}
            </p>
            <p className="text-muted-foreground">
              <strong>Entradas Reservadas:</strong>{" "}
              {formatter.format(currentCounts.reservadas || 0)}
            </p>
            <p className="text-muted-foreground">
              <strong>Entradas Vendidas:</strong>{" "}
              {formatter.format(currentCounts.vendidas || 0)}
            </p>
            <p className="text-muted-foreground">
              <strong>Entradas VIP:</strong>{" "}
              {formatter.format(currentCounts.vip || 0)}
            </p>
            <p className="text-muted-foreground">
              <strong>Total:</strong> {formatter.format(totalEntradas || 0)}
            </p>
          </div>

          <DialogFooter className="mt-6">
            <Button variant="outline" onClick={() => setShowSummary(false)}>
              Cancelar
            </Button>
            <Button
              onClick={handleConfirmClose}
              className="bg-primary text-white"
            >
              Confirmar Cierre
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ========================
// Componentes auxiliares
// ========================

interface MemoCounterProps {
  title: string;
  count: number;
  onIncrement: () => void;
  onDecrement: () => void;
  variant: StatCounterVariant;
  maxCount: number;
}

const MemoCounter = React.memo(function MemoCounter({
  title,
  count,
  onIncrement,
  onDecrement,
  variant,
  maxCount,
}: MemoCounterProps) {
  return (
    <StatCounter
      title={title}
      count={count}
      onIncrement={onIncrement}
      onDecrement={onDecrement}
      variant={variant}
      maxCount={maxCount}
    />
  );
});

interface ResumenBoxProps {
  icon: React.ElementType;
  title: string;
  value: number | undefined;
}

function ResumenBox({ icon: Icon, title, value }: ResumenBoxProps) {
  const safeValue = Number.isFinite(value) ? (value ?? 0) : 0;
  const formatter = useMemo(
    () => new Intl.NumberFormat("es-AR", { maximumFractionDigits: 0 }),
    []
  );
  const formattedValue = formatter.format(safeValue);

  return (
    <Card className="bg-card border rounded-lg p-6">
      <div className="flex items-center gap-3 mb-2">
        <Icon className="h-5 w-5 text-muted-foreground" />
        <p className="text-sm text-muted-foreground">{title}</p>
      </div>
      <p className="text-3xl font-bold text-foreground">{formattedValue}</p>
    </Card>
  );
}
