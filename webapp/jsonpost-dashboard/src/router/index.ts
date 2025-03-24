import { createRouter, createWebHashHistory } from 'vue-router';
import Dashboard from '../components/Dashboard.vue';
import DetailView from '../components/DetailView.vue';

console.log('Router running in hash mode');

const routes = [
  { path: '/', component: Dashboard },
  { path: '/detail/:uuid', component: DetailView, props: true }
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

export default router;
