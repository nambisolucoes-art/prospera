import type { EcommercePageDefinition } from "./Ecommerce.types";
export const ecommercePages: Record<string, EcommercePageDefinition> = {
  "ecommerce-dashboard.html": {
    key: "ecommerce-dashboard",
    kind: "dashboard",
    title: "Dashboard",
    breadcrumb: [],
  },
  "ecommerce-products.html": {
    key: "ecommerce-products",
    kind: "products",
    title: "Produtos",
    breadcrumb: ["Prospera"],
  },
  "ecommerce-product-detail.html": {
    key: "ecommerce-product-detail",
    kind: "product-detail",
    title: "Adicionar/editar produto",
    breadcrumb: ["Prospera", "eCommerce"],
  },
  "ecommerce-orders.html": {
    key: "ecommerce-orders",
    kind: "orders",
    title: "Pedidos",
    breadcrumb: ["Prospera", "eCommerce"],
  },
  "ecommerce-sellers.html": {
    key: "ecommerce-sellers",
    kind: "sellers",
    title: "Vendedores",
    breadcrumb: ["Prospera", "eCommerce"],
  },
};
