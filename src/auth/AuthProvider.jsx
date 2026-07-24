import { useEffect, useMemo, useState } from "react";
import { ApiError, authApi } from "../services/api";
import { AuthContext } from "./auth-context";

export default function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [sessionError, setSessionError] = useState("");
  const [demoAccounts, setDemoAccounts] = useState([]);
  const [demoLoading, setDemoLoading] = useState(true);
  const [demoError, setDemoError] = useState("");

  useEffect(() => {
    let active = true;

    async function restoreSession() {
      try {
        const currentUser = await authApi.me();
        if (active) setUser(currentUser);
      } catch (error) {
        if (
          active &&
          (!(error instanceof ApiError) || error.status !== 401)
        ) {
          setSessionError(error.message);
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    async function loadDemoAccounts() {
      try {
        const accounts = await authApi.demoAccounts();
        if (active) setDemoAccounts(accounts);
      } catch (error) {
        if (!active) return;

        if (error instanceof ApiError && error.status === 404) {
          setDemoAccounts([]);
        } else {
          setDemoError(
            error instanceof Error
              ? error.message
              : "Demo accounts could not be loaded.",
          );
        }
      } finally {
        if (active) setDemoLoading(false);
      }
    }

    restoreSession();
    loadDemoAccounts();

    return () => {
      active = false;
    };
  }, []);

  const value = useMemo(
    () => ({
      user,
      loading,
      sessionError,
      demoAccounts,
      demoLoading,
      demoError,
      async login(credentials) {
        const authenticatedUser = await authApi.login(credentials);

        if (!authenticatedUser) {
          throw new ApiError("The server did not return an authenticated user.");
        }

        setSessionError("");
        setUser(authenticatedUser);
        return authenticatedUser;
      },
      async logout() {
        try {
          await authApi.logout();
          setSessionError("");
          setUser(null);
        } catch (error) {
          if (error instanceof ApiError && error.status === 401) {
            setSessionError("");
            setUser(null);
            return;
          }

          throw error;
        }
      },
      async refreshUser() {
        const currentUser = await authApi.me();
        setUser(currentUser);
        return currentUser;
      },
    }),
    [
      demoAccounts,
      demoError,
      demoLoading,
      loading,
      sessionError,
      user,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
