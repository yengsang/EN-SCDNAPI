import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "EdgeNext API Explorer",
  description: "Browse EdgeNext endpoints by category, enter parameters, and inspect signed API responses.",
  other: {
    "codex-preview": "development",
  },
  icons: {
    icon: "/favicon.svg",
    shortcut: "/favicon.svg",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className="antialiased">{children}</body>
    </html>
  );
}
