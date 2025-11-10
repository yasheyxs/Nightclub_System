import { useState } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertCircle, CheckCircle, Minus, Plus } from "lucide-react";
import { cn } from "@/lib/utils";

interface Product {
  id: string;
  name: string;
  stock: number;
  minStock: number;
  category: string;
}

export default function Stock() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");
  
  const [products, setProducts] = useState<Record<Location, Product[]>>({
    santas: [
      { id: "1", name: "Fernet Branca 750ml", stock: 45, minStock: 20, category: "Bebidas" },
      { id: "2", name: "Coca Cola 1.5L", stock: 80, minStock: 50, category: "Bebidas" },
      { id: "3", name: "Cerveza Corona", stock: 15, minStock: 30, category: "Bebidas" },
      { id: "4", name: "Vodka Smirnoff", stock: 25, minStock: 15, category: "Bebidas" },
      { id: "5", name: "Hielo (bolsa 5kg)", stock: 8, minStock: 10, category: "Suministros" },
      { id: "6", name: "Vasos descartables", stock: 500, minStock: 200, category: "Suministros" },
    ],
    outdoor: [
      { id: "7", name: "Campari", stock: 12, minStock: 10, category: "Bebidas" },
      { id: "8", name: "Sprite 1.5L", stock: 40, minStock: 30, category: "Bebidas" },
      { id: "9", name: "Ron Bacardi", stock: 18, minStock: 15, category: "Bebidas" },
      { id: "10", name: "Hielo (bolsa 5kg)", stock: 6, minStock: 8, category: "Suministros" },
    ],
    kiddo: [
      { id: "11", name: "Jugo de naranja", stock: 25, minStock: 15, category: "Bebidas" },
      { id: "12", name: "Gaseosas variadas", stock: 40, minStock: 30, category: "Bebidas" },
      { id: "13", name: "Vasos plásticos infantiles", stock: 150, minStock: 100, category: "Suministros" },
    ],
  });

  const updateStock = (productId: string, change: number) => {
    setProducts((prev) => ({
      ...prev,
      [selectedLocation]: prev[selectedLocation].map((p) =>
        p.id === productId ? { ...p, stock: Math.max(0, p.stock + change) } : p
      ),
    }));
  };

  const getStockStatus = (product: Product) => {
    if (product.stock === 0) return { label: "Agotado", color: "destructive", icon: AlertCircle };
    if (product.stock < product.minStock) return { label: "Bajo", color: "warning", icon: AlertCircle };
    return { label: "OK", color: "success", icon: CheckCircle };
  };

  const currentProducts = products[selectedLocation];
  const lowStockCount = currentProducts.filter((p) => p.stock < p.minStock).length;
  const outOfStockCount = currentProducts.filter((p) => p.stock === 0).length;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Gestión de Stock</h1>
          <p className="text-muted-foreground">Control de inventario en tiempo real</p>
        </div>
        <LocationSelector selectedLocation={selectedLocation} onLocationChange={setSelectedLocation} />
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className="border-border">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Total Productos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-foreground">{currentProducts.length}</div>
          </CardContent>
        </Card>
        <Card className="border-warning/50 bg-warning/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Stock Bajo</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-warning">{lowStockCount}</div>
          </CardContent>
        </Card>
        <Card className="border-destructive/50 bg-destructive/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Agotados</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-destructive">{outOfStockCount}</div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-3">
        {currentProducts.map((product) => {
          const status = getStockStatus(product);
          const StatusIcon = status.icon;

          return (
            <Card
              key={product.id}
              className={cn(
                "transition-all duration-200",
                status.color === "destructive" && "border-destructive/50 bg-destructive/5",
                status.color === "warning" && "border-warning/50 bg-warning/5"
              )}
            >
              <CardContent className="p-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="text-xl font-semibold text-foreground">{product.name}</h3>
                      <span className="text-sm text-muted-foreground px-2 py-1 bg-muted rounded">
                        {product.category}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <StatusIcon className={cn("h-4 w-4", `text-${status.color}`)} />
                      <span className={cn("text-sm font-medium", `text-${status.color}`)}>
                        {status.label}
                      </span>
                      <span className="text-sm text-muted-foreground">
                        · Stock mínimo: {product.minStock}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-center min-w-[80px]">
                      <p className="text-sm text-muted-foreground mb-1">Stock Actual</p>
                      <p className="text-4xl font-bold text-primary">{product.stock}</p>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        onClick={() => updateStock(product.id, -1)}
                        disabled={product.stock === 0}
                        size="lg"
                        variant="outline"
                        className="h-14 w-14"
                      >
                        <Minus className="h-6 w-6" />
                      </Button>
                      <Button
                        onClick={() => updateStock(product.id, 1)}
                        size="lg"
                        className="h-14 w-14 bg-gradient-primary"
                      >
                        <Plus className="h-6 w-6" />
                      </Button>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
