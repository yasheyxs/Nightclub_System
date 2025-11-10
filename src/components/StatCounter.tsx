import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Minus, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { useMemo } from "react";

interface StatCounterProps {
  title: string;
  count: number;
  onIncrement: () => void;
  onDecrement: () => void;
  variant?: "default" | "primary" | "accent" | "success";
  maxCount?: number;
}

export function StatCounter({
  title,
  count,
  onIncrement,
  onDecrement,
  variant = "default",
  maxCount,
}: StatCounterProps) {
  const variantStyles = {
    default: "border-border",
    primary: "border-primary/50 bg-primary/5",
    accent: "border-accent/50 bg-accent/5",
    success: "border-success/50 bg-success/5",
  };

  // 🧠 Aseguramos que count y maxCount sean números válidos
  const safeCount = Number.isFinite(count) ? count : 0;
  const safeMax = Number.isFinite(maxCount ?? 0) ? (maxCount ?? 0) : 0;

  const formatter = useMemo(
    () => new Intl.NumberFormat("es-AR", { maximumFractionDigits: 0 }),
    []
  );

  const formattedCount = formatter.format(safeCount);
  const formattedMax = safeMax ? formatter.format(safeMax) : null;

  // 🔹 Evita divisiones por 0 → si no hay maxCount, el porcentaje es 0
  const percentage =
    safeMax > 0 ? Math.min(Math.round((safeCount / safeMax) * 100), 100) : 0;

  const isNearCapacity = percentage >= 80;

  return (
    <Card className={cn("transition-all duration-300", variantStyles[variant])}>
      <CardHeader>
        <CardTitle className="text-lg font-semibold text-foreground">
          {title}
        </CardTitle>
      </CardHeader>

      <CardContent className="space-y-4">
        <div className="text-center">
          {/* ✅ Nunca NaN */}
          <div className="text-6xl font-bold text-primary mb-2 leading-none">
            {formattedCount}
          </div>

          {safeMax > 0 && (
            <div className="flex items-center justify-center gap-2">
              <div className="text-sm text-muted-foreground">
                de {formattedMax}
              </div>
              <div
                className={cn(
                  "text-sm font-semibold px-2 py-1 rounded transition-colors",
                  isNearCapacity
                    ? "bg-warning/20 text-warning"
                    : "bg-success/20 text-success"
                )}
              >
                {percentage}%
              </div>
            </div>
          )}
        </div>

        {/* BOTONES */}
        <div className="flex gap-3 mt-4">
          <Button
            onClick={onDecrement}
            disabled={safeCount <= 0}
            size="lg"
            variant="outline"
            className="flex-1 h-16 text-xl font-bold"
          >
            <Minus className="h-8 w-8" />
          </Button>

          <Button
            onClick={onIncrement}
            disabled={safeMax ? safeCount >= safeMax : false}
            size="lg"
            className="flex-1 h-16 text-xl font-bold bg-gradient-primary hover:opacity-90"
          >
            <Plus className="h-8 w-8" />
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
