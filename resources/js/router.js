import { createRouter, createWebHistory } from 'vue-router';

const titles = { dashboard: 'Vezérlőpult', orders: 'Rendelések', inventory: 'Készlet', customers: 'Ügyfelek', invoices: 'Számlák', channels: 'Webshop' };

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'dashboard', component: () => import('./views/Dashboard.vue') },
        { path: '/orders/:id?', name: 'orders', component: () => import('./views/Orders.vue') },
        { path: '/inventory/:id?', name: 'inventory', component: () => import('./views/Inventory.vue') },
        { path: '/customers/:id?', name: 'customers', component: () => import('./views/Customers.vue') },
        { path: '/invoices/:id?', name: 'invoices', component: () => import('./views/Invoices.vue') },
        { path: '/channels', name: 'channels', component: () => import('./views/Channels.vue') },
        { path: '/:rest(.*)*', redirect: '/' },
    ],
    scrollBehavior: (to, from) => (to.name !== from.name ? { top: 0 } : false),
});

router.afterEach((to) => {
    document.title = `${titles[to.name] ?? 'Mini-ERP'} · Mini-ERP`;
});

export default router;
