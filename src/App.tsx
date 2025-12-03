import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Layout } from "@/components/Layout";
import Index from "./pages/Index";
import Entradas from "./pages/Entradas";
import Eventos from "./pages/Eventos";
import Listas from "./pages/Listas";
import Anticipadas from "./pages/Anticipadas";
import Configuracion from "./pages/Configuracion";
import Usuarios from "./pages/Usuarios";
import NotFound from "./pages/NotFound";
import Login from "./pages/Login";
import ForgotPassword from "./pages/ForgotPassword";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { AuthProvider } from "@/context/AuthContext";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/recuperar" element={<ForgotPassword />} />
            <Route element={<ProtectedRoute />}>
              <Route element={<Layout />}>
                <Route path="/" element={<Index />} />
                <Route path="/entradas" element={<Entradas />} />
                <Route path="/eventos" element={<Eventos />} />
                <Route path="/listas" element={<Listas />} />
                <Route path="/anticipadas" element={<Anticipadas />} />
                <Route path="/usuarios" element={<Usuarios />} />
                <Route path="/configuracion" element={<Configuracion />} />
                <Route path="*" element={<NotFound />} />
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
