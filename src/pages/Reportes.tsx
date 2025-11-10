import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { BarChart3, TrendingUp, Calendar, DollarSign, Download, FileSpreadsheet } from "lucide-react";
import { toast } from "@/hooks/use-toast";

export default function Reportes() {
  const handleDownloadPDF = () => {
    toast({
      title: "Descargando PDF",
      description: "El reporte se está generando...",
    });
  };

  const handleDownloadExcel = () => {
    toast({
      title: "Descargando Excel",
      description: "El archivo se está generando...",
    });
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Reportes y Métricas</h1>
          <p className="text-muted-foreground">Análisis consolidado del negocio</p>
        </div>
        <div className="flex gap-3">
          <Button onClick={handleDownloadPDF} size="lg" variant="outline" className="gap-2">
            <Download className="h-5 w-5" />
            <span className="hidden lg:inline">Descargar PDF</span>
          </Button>
          <Button onClick={handleDownloadExcel} size="lg" className="gap-2">
            <FileSpreadsheet className="h-5 w-5" />
            <span className="hidden lg:inline">Descargar Excel</span>
          </Button>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="border-primary/50 bg-primary/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <BarChart3 className="h-4 w-4 text-primary" />
              Entradas del Mes
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-primary">1,245</div>
            <p className="text-sm text-success mt-1">+12% vs mes anterior</p>
          </CardContent>
        </Card>

        <Card className="border-accent/50 bg-accent/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <DollarSign className="h-4 w-4 text-accent" />
              Recaudación
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-accent">$622K</div>
            <p className="text-sm text-success mt-1">+18% vs mes anterior</p>
          </CardContent>
        </Card>

        <Card className="border-success/50 bg-success/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <TrendingUp className="h-4 w-4 text-success" />
              Ocupación Promedio
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-success">78%</div>
            <p className="text-sm text-muted-foreground mt-1">Últimos 30 días</p>
          </CardContent>
        </Card>

        <Card className="border-border">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <Calendar className="h-4 w-4 text-foreground" />
              Eventos Realizados
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-foreground">24</div>
            <p className="text-sm text-muted-foreground mt-1">Este mes</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Ventas por Sucursal</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Santas Indoor</span>
                <span className="font-bold text-primary">620 entradas (50%)</span>
              </div>
              <div className="h-3 bg-muted rounded-full overflow-hidden">
                <div className="h-full bg-gradient-primary" style={{ width: "50%" }} />
              </div>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Santas Outdoor</span>
                <span className="font-bold text-accent">435 entradas (35%)</span>
              </div>
              <div className="h-3 bg-muted rounded-full overflow-hidden">
                <div className="h-full bg-accent" style={{ width: "35%" }} />
              </div>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Kiddo</span>
                <span className="font-bold text-success">190 entradas (15%)</span>
              </div>
              <div className="h-3 bg-muted rounded-full overflow-hidden">
                <div className="h-full bg-success" style={{ width: "15%" }} />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Top Promotores</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-3 bg-accent/10 rounded-lg">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-accent flex items-center justify-center text-accent-foreground font-bold">
                  1
                </div>
                <div>
                  <p className="font-semibold text-foreground">Juan Pérez</p>
                  <p className="text-sm text-muted-foreground">145 entradas</p>
                </div>
              </div>
              <div className="text-right">
                <p className="font-bold text-success">$72.5K</p>
              </div>
            </div>
            <div className="flex items-center justify-between p-3 bg-muted rounded-lg">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold">
                  2
                </div>
                <div>
                  <p className="font-semibold text-foreground">María García</p>
                  <p className="text-sm text-muted-foreground">132 entradas</p>
                </div>
              </div>
              <div className="text-right">
                <p className="font-bold text-success">$66K</p>
              </div>
            </div>
            <div className="flex items-center justify-between p-3 bg-muted rounded-lg">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                  3
                </div>
                <div>
                  <p className="font-semibold text-foreground">Carlos Rodríguez</p>
                  <p className="text-sm text-muted-foreground">118 entradas</p>
                </div>
              </div>
              <div className="text-right">
                <p className="font-bold text-success">$59K</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Productos Más Vendidos</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {[
              { name: "Fernet Branca", sold: 320, revenue: "$48K" },
              { name: "Cerveza Corona", sold: 280, revenue: "$42K" },
              { name: "Vodka Smirnoff", sold: 195, revenue: "$29K" },
              { name: "Ron Bacardi", sold: 150, revenue: "$22K" },
            ].map((product) => (
              <div key={product.name} className="p-4 bg-card border border-border rounded-lg">
                <h4 className="font-semibold text-foreground mb-2">{product.name}</h4>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-muted-foreground">{product.sold} unidades</span>
                  <span className="text-lg font-bold text-primary">{product.revenue}</span>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
