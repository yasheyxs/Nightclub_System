import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { LucideIcon } from "lucide-react";
import { cn } from "@/lib/utils";

interface MetricCardProps {
  title: string;
  value: string | number;
  icon: LucideIcon;
  trend?: {
    value: string;
    positive: boolean;
  };
  variant?: "default" | "primary" | "accent" | "success" | "warning" | "destructive";
  className?: string;
}

export function MetricCard({
  title,
  value,
  icon: Icon,
  trend,
  variant = "default",
  className,
}: MetricCardProps) {
  const variantStyles = {
    default: "border-border",
    primary: "border-primary/50 bg-primary/5",
    accent: "border-accent/50 bg-accent/5",
    success: "border-success/50 bg-success/5",
    warning: "border-warning/50 bg-warning/5",
    destructive: "border-destructive/50 bg-destructive/5",
  };

  const iconVariantStyles = {
    default: "text-muted-foreground",
    primary: "text-primary",
    accent: "text-accent",
    success: "text-success",
    warning: "text-warning",
    destructive: "text-destructive",
  };

  return (
    <Card className={cn("transition-all duration-300 hover:shadow-lg", variantStyles[variant], className)}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wide">
          {title}
        </CardTitle>
        <Icon className={cn("h-5 w-5", iconVariantStyles[variant])} />
      </CardHeader>
      <CardContent>
        <div className="text-4xl font-bold text-foreground">{value}</div>
        {trend && (
          <p className={cn("text-sm mt-2", trend.positive ? "text-success" : "text-destructive")}>
            {trend.value}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
