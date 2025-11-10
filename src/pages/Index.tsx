import { useState } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { MetricCard } from "@/components/MetricCard";
import { Ticket, DollarSign, TrendingUp, Calendar, Package, AlertCircle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function Index() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");

  const metrics = {
    santas: { entradas: 180, recaudacion: 90000, ocupacion: 60, stock: 3 },
    outdoor: { entradas: 123, recaudacion: 61500, ocupacion: 62, stock: 1 },
    kiddo: { entradas: 47, recaudacion: 23500, ocupacion: 59, stock: 0 },
  };

  const currentMetrics = metrics[selectedLocation];

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-5xl font-bold bg-gradient-primary bg-clip-text text-transparent mb-2">
            Dashboard
          </h1>
          <p className="text-muted-foreground text-lg">Control en tiempo real de todas las operaciones</p>
        </div>
        <LocationSelector selectedLocation={selectedLocation} onLocationChange={setSelectedLocation} />
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Entradas Vendidas"
          value={currentMetrics.entradas}
          icon={Ticket}
          variant="primary"
          trend={{ value: "+12% vs ayer", positive: true }}
        />
        <MetricCard
          title="Recaudación Estimada"
          value={`$${(currentMetrics.recaudacion / 1000).toFixed(0)}K`}
          icon={DollarSign}
          variant="accent"
          trend={{ value: "+18% vs ayer", positive: true }}
        />
        <MetricCard
          title="Ocupación"
          value={`${currentMetrics.ocupacion}%`}
          icon={TrendingUp}
          variant="success"
        />
        <MetricCard
          title="Alertas de Stock"
          value={currentMetrics.stock}
          icon={AlertCircle}
          variant={currentMetrics.stock > 0 ? "warning" : "default"}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="border-border">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5 text-primary" />
              Próximos Eventos
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {[
              { title: "Noche de DJ Internacional", date: "15 Nov", location: "Santas", ocupacion: 82 },
              { title: "Pool Party Sunset", date: "16 Nov", location: "Outdoor", ocupacion: 75 },
              { title: "Cumpleaños Temático", date: "17 Nov", location: "Kiddo", ocupacion: 70 },
            ].map((event, i) => (
              <div key={i} className="flex items-center justify-between p-3 bg-card border border-border rounded-lg">
                <div>
                  <p className="font-semibold text-foreground">{event.title}</p>
                  <p className="text-sm text-muted-foreground">
                    {event.date} · {event.location}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-2xl font-bold text-primary">{event.ocupacion}%</p>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card className="border-border">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Package className="h-5 w-5 text-warning" />
              Stock Crítico
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {[
              { product: "Cerveza Corona", stock: 15, min: 30, status: "bajo" },
              { product: "Hielo 5kg", stock: 8, min: 10, status: "bajo" },
              { product: "Ron Bacardi", stock: 12, min: 15, status: "bajo" },
            ].map((item, i) => (
              <div key={i} className="flex items-center justify-between p-3 bg-warning/10 border border-warning/30 rounded-lg">
                <div className="flex-1">
                  <p className="font-semibold text-foreground">{item.product}</p>
                  <p className="text-sm text-muted-foreground">Mínimo: {item.min} unidades</p>
                </div>
                <div className="text-right">
                  <p className="text-2xl font-bold text-warning">{item.stock}</p>
                  <p className="text-xs uppercase text-warning font-semibold">Bajo</p>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <Card className="border-primary/30 bg-primary/5">
        <CardHeader>
          <CardTitle>Resumen Global - Todas las Sucursales</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-3">
            <div className="text-center p-4">
              <p className="text-sm text-muted-foreground mb-2">Total Entradas Hoy</p>
              <p className="text-4xl font-bold text-primary">350</p>
            </div>
            <div className="text-center p-4">
              <p className="text-sm text-muted-foreground mb-2">Recaudación Total</p>
              <p className="text-4xl font-bold text-accent">$175K</p>
            </div>
            <div className="text-center p-4">
              <p className="text-sm text-muted-foreground mb-2">Ocupación Promedio</p>
              <p className="text-4xl font-bold text-success">60%</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
