import { useEffect, useMemo, useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";
import { Loader2, Printer, Search, Ticket } from "lucide-react";

interface AnticipadaResponse {
  id: number;
  nombre: string;
  dni?: string | null;
  entrada_id: number;
  evento_id?: number | null;
  cantidad?: number | null;
  incluye_trago?: boolean;
  entrada_nombre?: string | null;
  entrada_precio?: number | null;
  evento_nombre?: string | null;
}

interface AnticipadaItem {
  id: number;
  nombre: string;
  dni: string;
  entradaNombre: string;
  cantidad: number;
  incluyeTrago: boolean;
  eventoNombre: string;
}

export default function Anticipadas() {
  const [anticipadas, setAnticipadas] = useState<AnticipadaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [printingId, setPrintingId] = useState<number | null>(null);
  const [search, setSearch] = useState("");

  useEffect(() => {
    const fetchAnticipadas = async () => {
      setLoading(true);
      try {
        const { data } = await api.get<AnticipadaResponse[]>(
          "/anticipadas.php"
        );

        const mapped: AnticipadaItem[] = (data ?? []).map((item) => ({
          id: Number(item.id),
          nombre: item.nombre ?? "Sin nombre",
          dni: item.dni ?? "-",
          entradaNombre: item.entrada_nombre ?? "Anticipada",
          cantidad: Number(item.cantidad ?? 1),
          incluyeTrago: Boolean(item.incluye_trago),
          eventoNombre: item.evento_nombre ?? "—",
        }));

        setAnticipadas(mapped);
      } catch (error) {
        console.error("Error cargando anticipadas:", error);
        toast({
          title: "Error",
          description: "No se pudieron cargar las anticipadas.",
          variant: "destructive",
        });
      } finally {
        setLoading(false);
      }
    };

    fetchAnticipadas();
  }, []);

  const filteredAnticipadas = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return anticipadas;
    return anticipadas.filter((item) =>
      `${item.nombre} ${item.dni} ${item.eventoNombre} ${item.entradaNombre}`
        .toLowerCase()
        .includes(query)
    );
  }, [anticipadas, search]);

  const totalPorImprimir = anticipadas.reduce(
    (acc, item) => acc + item.cantidad,
    0
  );

  const handlePrint = async (itemId: number) => {
    setPrintingId(itemId);
    try {
      const { data } = await api.post("/anticipadas.php", {
        accion: "imprimir",
        id: itemId,
      });

      const mensaje =
        typeof data?.mensaje === "string"
          ? data.mensaje
          : "Ticket enviado a impresión.";

      setAnticipadas((prev) => prev.filter((item) => item.id !== itemId));
      toast({
        title: "Ticket impreso",
        description: mensaje,
      });
    } catch (error) {
      console.error("Error al imprimir anticipada:", error);
      toast({
        title: "No se pudo imprimir",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setPrintingId(null);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Anticipadas
          </h1>
          <p className="text-muted-foreground">
            Gestioná las compras anticipadas y enviá los tickets a impresión.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">
              Anticipadas cargadas
            </p>
            <div className="flex items-center gap-2">
              <Ticket className="h-4 w-4 text-primary" />
              <p className="text-2xl font-bold">{anticipadas.length}</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Tickets a imprimir</p>
            <p className="text-2xl font-bold">{totalPorImprimir}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Filtrar</p>
            <div className="relative">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Buscar por nombre, DNI o evento"
                className="pl-8"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="bg-card border border-border rounded-lg p-4">
        <div className="flex items-center justify-between mb-3">
          <div>
            <p className="text-lg font-semibold">Pendientes de impresión</p>
            <p className="text-sm text-muted-foreground">
              Lista de personas con compras anticipadas listas para entregar.
            </p>
          </div>
        </div>

        {loading ? (
          <div className="flex items-center text-muted-foreground text-sm py-10">
            <Loader2 className="h-4 w-4 animate-spin mr-2" /> Cargando
            anticipadas...
          </div>
        ) : filteredAnticipadas.length === 0 ? (
          <p className="text-sm text-muted-foreground py-6">
            No hay anticipadas para mostrar.
          </p>
        ) : (
          <ScrollArea className="h-[480px]">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>DNI</TableHead>
                  <TableHead>Entrada</TableHead>
                  <TableHead className="text-center">Cantidad</TableHead>
                  <TableHead>Evento</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredAnticipadas.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <div className="font-semibold text-foreground">
                        {item.nombre}
                      </div>
                      <p className="text-xs text-muted-foreground">
                        Anticipada registrada
                      </p>
                    </TableCell>
                    <TableCell>{item.dni}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Badge variant="secondary">{item.entradaNombre}</Badge>
                        {item.incluyeTrago && (
                          <Badge variant="outline">+ Trago</Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="text-center font-semibold">
                      {item.cantidad}
                    </TableCell>
                    <TableCell>{item.eventoNombre}</TableCell>
                    <TableCell className="text-right">
                      <Button
                        size="sm"
                        variant="secondary"
                        disabled={printingId === item.id}
                        onClick={() => handlePrint(item.id)}
                      >
                        {printingId === item.id ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Printer className="h-4 w-4" />
                        )}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </ScrollArea>
        )}
      </div>
    </div>
  );
}
