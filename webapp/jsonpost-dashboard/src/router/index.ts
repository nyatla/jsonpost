import { createRouter, createWebHistory } from 'vue-router';
import Dashboard from '../components/Dashboard.vue';
import DetailView from '../components/DetailView.vue';

const routes = [
  { path: '/', component: Dashboard },
  { path: '/detail/:uuid', component: DetailView, props: true }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;