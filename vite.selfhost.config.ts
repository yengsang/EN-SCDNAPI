import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({
  root:'selfhost/frontend',
  publicDir:'../../public',
  plugins:[react()],
  build:{outDir:'../../dist/selfhost',emptyOutDir:true},
  server:{host:'127.0.0.1',port:5173,strictPort:true,proxy:{'/api':'http://127.0.0.1:8081'}},
});
