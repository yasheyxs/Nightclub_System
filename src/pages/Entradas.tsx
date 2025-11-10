import { useState } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { StatCounter } from "@/components/StatCounter";
import { Ticket, Users, Crown } from "lucide-react";

export default function Entradas() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");
  
  const [counters, setCounters] = useState({
    santas: { reservadas: 45, vendidas: 120, vip: 15 },
    outdoor: { reservadas: 30, vendidas: 85, vip: 8 },
    kiddo: { reservadas: 12, vendidas: 35, vip: 0 },
  });

  const maxCapacity = {
    santas: 300,
    outdoor: 200,
    kiddo: 80,
  };

  const handleIncrement = (type: "reservadas" | "vendidas" | "vip") => {
    setCounters((prev) => ({
      ...prev,
      [selectedLocation]: {
        ...prev[selectedLocation],
        [type]: prev[selectedLocation][type] + 1,
      },
    }));
  };

  const handleDecrement = (type: "reservadas" | "vendidas" | "vip") => {
    setCounters((prev) => ({
      ...prev,
      [selectedLocation]: {
        ...prev[selectedLocation],
        [type]: Math.max(0, prev[selectedLocation][type] - 1),
      },
    }));
  };

  const currentCounts = counters[selectedLocation];
  const totalEntradas = currentCounts.reservadas + currentCounts.vendidas + currentCounts.vip;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Control de Entradas</h1>
          <p className="text-muted-foreground">Gestión en tiempo real del aforo</p>
        </div>
        <LocationSelector selectedLocation={selectedLocation} onLocationChange={setSelectedLocation} />
      </div>

      <div className="bg-card border border-border rounded-lg p-6 mb-6">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-muted-foreground uppercase tracking-wide">Aforo Total</p>
            <p className="text-5xl font-bold text-primary">{totalEntradas}</p>
          </div>
          <div className="text-right">
            <p className="text-sm text-muted-foreground">Capacidad Máxima</p>
            <p className="text-2xl font-semibold text-foreground">{maxCapacity[selectedLocation]}</p>
            <div className="mt-2">
              <div className="w-48 h-3 bg-muted rounded-full overflow-hidden">
                <div
                  className="h-full bg-gradient-primary transition-all duration-500"
                  style={{ width: `${Math.min((totalEntradas / maxCapacity[selectedLocation]) * 100, 100)}%` }}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <StatCounter
          title="Entradas Reservadas"
          count={currentCounts.reservadas}
          onIncrement={() => handleIncrement("reservadas")}
          onDecrement={() => handleDecrement("reservadas")}
          variant="primary"
          maxCount={maxCapacity[selectedLocation]}
        />
        <StatCounter
          title="Entradas Vendidas"
          count={currentCounts.vendidas}
          onIncrement={() => handleIncrement("vendidas")}
          onDecrement={() => handleDecrement("vendidas")}
          variant="accent"
          maxCount={maxCapacity[selectedLocation]}
        />
        <StatCounter
          title="Entradas VIP"
          count={currentCounts.vip}
          onIncrement={() => handleIncrement("vip")}
          onDecrement={() => handleDecrement("vip")}
          variant="success"
          maxCount={Math.floor(maxCapacity[selectedLocation] * 0.2)}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-3 mt-8">
        <div className="bg-card border border-primary/30 rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <Ticket className="h-5 w-5 text-primary" />
            <p className="text-sm text-muted-foreground">Reservadas</p>
          </div>
          <p className="text-3xl font-bold text-foreground">{currentCounts.reservadas}</p>
        </div>
        <div className="bg-card border border-accent/30 rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <Users className="h-5 w-5 text-accent" />
            <p className="text-sm text-muted-foreground">En el momento</p>
          </div>
          <p className="text-3xl font-bold text-foreground">{currentCounts.vendidas}</p>
        </div>
        <div className="bg-card border border-success/30 rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <Crown className="h-5 w-5 text-success" />
            <p className="text-sm text-muted-foreground">VIP</p>
          </div>
          <p className="text-3xl font-bold text-foreground">{currentCounts.vip}</p>
        </div>
      </div>
    </div>
  );
}
