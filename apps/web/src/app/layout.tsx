import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "QueueCare | Staff and Admin",
  description: "QueueCare staff and admin portal",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
