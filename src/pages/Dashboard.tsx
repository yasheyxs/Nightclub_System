import { useEffect, useState } from "react";
import { KPICard } from "@/components/KPICard";
import {
  DollarSign,
  Users,
  Calendar,
  TrendingUp,
  Activity,
  Package,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
} from "recharts";

export default function Dashboard() {
  const [loading, setLoading] = useState(true);
  const [kpis, setKpis] = useState({
    ventasHoy: null,
    clientesActivos: null,
    eventosMes: null,
    productosVendidos: null,
  });
  const [salesData, setSalesData] = useState([]);
  const [attendanceData, setAttendanceData] = useState([]);
  const [categoryData, setCategoryData] = useState([]);
  const [recentActivity, setRecentActivity] = useState([]);

  useEffect(() => {
    // TODO: integrar con backend real
    // fetch("/api/dashboard")
    //   .then((res) => res.json())
    //   .then((data) => {
    //     setKpis(data.kpis);
    //     setSalesData(data.salesData);
    //     setAttendanceData(data.attendanceData);
    //     setCategoryData(data.categoryData);
    //     setRecentActivity(data.recentActivity);
    //   })
    //   .finally(() => setLoading(false));
    setLoading(false);
  }, []);

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h2 className="text-3xl font-bold text-foreground mb-2">Dashboard</h2>
        <p className="text-muted-foreground">Resumen general del negocio</p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <KPICard
          title="Ventas Hoy"
          value={
            loading ? "—" : kpis.ventasHoy !== null ? `$${kpis.ventasHoy}` : "—"
          }
          icon={DollarSign}
        />
        <KPICard
          title="Clientes Activos"
          value={
            loading
              ? "—"
              : kpis.clientesActivos !== null
                ? kpis.clientesActivos
                : "—"
          }
          icon={Users}
        />
        <KPICard
          title="Eventos Este Mes"
          value={
            loading ? "—" : kpis.eventosMes !== null ? kpis.eventosMes : "—"
          }
          icon={Calendar}
        />
        <KPICard
          title="Productos Vendidos"
          value={
            loading
              ? "—"
              : kpis.productosVendidos !== null
                ? kpis.productosVendidos
                : "—"
          }
          icon={TrendingUp}
        />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card className="bg-gradient-card border-border/50 shadow-card">
          <CardHeader>
            <CardTitle className="text-foreground flex items-center gap-2">
              <DollarSign className="w-5 h-5 text-primary" />
              Ventas de la Semana
            </CardTitle>
          </CardHeader>
          <CardContent>
            {salesData.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Sin datos disponibles
              </p>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={salesData}>
                  <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="hsl(245 15% 18%)"
                  />
                  <XAxis dataKey="name" stroke="hsl(0 0% 65%)" />
                  <YAxis stroke="hsl(0 0% 65%)" />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "hsl(245 15% 13%)",
                      border: "1px solid hsl(245 15% 18%)",
                      borderRadius: "8px",
                    }}
                  />
                  <Bar
                    dataKey="ventas"
                    fill="hsl(245 100% 69%)"
                    radius={[8, 8, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        <Card className="bg-gradient-card border-border/50 shadow-card">
          <CardHeader>
            <CardTitle className="text-foreground flex items-center gap-2">
              <Users className="w-5 h-5 text-accent" />
              Asistencia Semanal
            </CardTitle>
          </CardHeader>
          <CardContent>
            {attendanceData.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Sin datos disponibles
              </p>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={attendanceData}>
                  <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="hsl(245 15% 18%)"
                  />
                  <XAxis dataKey="name" stroke="hsl(0 0% 65%)" />
                  <YAxis stroke="hsl(0 0% 65%)" />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "hsl(245 15% 13%)",
                      border: "1px solid hsl(245 15% 18%)",
                      borderRadius: "8px",
                    }}
                  />
                  <Line
                    type="monotone"
                    dataKey="asistencia"
                    stroke="hsl(300 100% 50%)"
                    strokeWidth={3}
                    dot={{ fill: "hsl(300 100% 50%)", r: 4 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Actividad y Categorías */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card className="lg:col-span-2 bg-gradient-card border-border/50 shadow-card">
          <CardHeader>
            <CardTitle className="text-foreground flex items-center gap-2">
              <Activity className="w-5 h-5 text-primary" />
              Actividad Reciente
            </CardTitle>
          </CardHeader>
          <CardContent>
            {recentActivity.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Sin actividad reciente
              </p>
            ) : (
              <div className="space-y-4">
                {recentActivity.map((activity) => (
                  <div
                    key={activity.id}
                    className="flex items-start gap-4 p-3 rounded-lg bg-surface-elevated hover:bg-surface-elevated/80 transition-colors"
                  >
                    <div
                      className={`w-2 h-2 mt-2 rounded-full ${
                        activity.color?.replace("text-", "bg-") || "bg-primary"
                      }`}
                    ></div>
                    <div className="flex-1">
                      <p className="font-medium text-foreground">
                        {activity.type}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {activity.description}
                      </p>
                    </div>
                    <span className="text-xs text-muted-foreground">
                      {activity.time}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="bg-gradient-card border-border/50 shadow-card">
          <CardHeader>
            <CardTitle className="text-foreground flex items-center gap-2">
              <Package className="w-5 h-5 text-accent" />
              Ventas por Categoría
            </CardTitle>
          </CardHeader>
          <CardContent>
            {categoryData.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Sin datos disponibles
              </p>
            ) : (
              <>
                <ResponsiveContainer width="100%" height={250}>
                  <PieChart>
                    <Pie
                      data={categoryData}
                      cx="50%"
                      cy="50%"
                      innerRadius={60}
                      outerRadius={90}
                      paddingAngle={5}
                      dataKey="value"
                    >
                      {categoryData.map((entry, index) => (
                        <Cell key={index} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip
                      contentStyle={{
                        backgroundColor: "hsl(245 15% 13%)",
                        border: "1px solid hsl(245 15% 18%)",
                        borderRadius: "8px",
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
                <div className="mt-4 space-y-2">
                  {categoryData.map((item) => (
                    <div
                      key={item.name}
                      className="flex items-center justify-between"
                    >
                      <div className="flex items-center gap-2">
                        <div
                          className="w-3 h-3 rounded-full"
                          style={{ backgroundColor: item.color }}
                        ></div>
                        <span className="text-sm text-foreground">
                          {item.name}
                        </span>
                      </div>
                      <span className="text-sm font-medium text-muted-foreground">
                        {item.value}%
                      </span>
                    </div>
                  ))}
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
