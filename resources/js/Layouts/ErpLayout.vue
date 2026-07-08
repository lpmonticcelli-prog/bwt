<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// Controle do Menu Mobile
const sidebarOpen = ref(false);
const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };

// Obtém os dados da página atual para marcar o menu ativo
const page = usePage();
</script>

<template>
    <div class="flex h-screen bg-slate-50 font-sans selection:bg-blue-500 selection:text-white overflow-hidden">
        
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 shadow-2xl transition-transform duration-300 md:translate-x-0 md:static md:flex md:flex-col overflow-y-auto scrollbar-hide">
            
            <div class="flex items-center justify-center h-20 border-b border-slate-800 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-black text-xl shadow-[0_0_15px_rgba(37,99,235,0.5)]">B</div>
                    <span class="text-xl font-black text-white tracking-tight">BWT <span class="text-blue-400 font-light">Auditor</span></span>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-8">
                
                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Visão Geral</p>
                    <Link href="/dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all" :class="page.url.startsWith('/dashboard') ? 'bg-blue-600 text-white shadow-[0_4px_12px_rgba(37,99,235,0.4)]' : 'text-slate-400 hover:text-white hover:bg-slate-800'">
                        <svg class="w-5 h-5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span class="text-sm font-semibold">Painel Executivo</span>
                    </Link>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Auditoria & Operação</p>
                    <div class="space-y-1">
                        <Link href="/auditoria" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/auditoria') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg">📉</span>
                            <span class="text-sm font-medium">Auditoria E4LOG</span>
                        </Link>
                        <Link href="/faturamento" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/faturamento') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg">📈</span>
                            <span class="text-sm font-medium">Faturamento Sol Fácil</span>
                        </Link>
                        <Link href="/fechamentos" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/fechamentos') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg">📤</span>
                            <span class="text-sm font-medium">Lançamentos (Upload)</span>
                        </Link>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3 flex items-center gap-2">Módulos Pro <span class="bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded text-[8px]">NOVO</span></p>
                    <div class="space-y-1">
                        <Link href="/simulador-contratos" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/simulador-contratos') ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg transition-opacity" :class="page.url.startsWith('/simulador-contratos') ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'">⏱️</span> 
                            <span class="text-sm font-medium">Simulador Contratos</span>
                        </Link>
                        
                        <Link href="/auditoria-sla" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/auditoria-sla') ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg transition-opacity" :class="page.url.startsWith('/auditoria-sla') ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'">⏳</span> 
                            <span class="text-sm font-medium">Auditoria SLA</span>
                        </Link>
                        
                        <Link href="/robo-whatsapp" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/robo-whatsapp') ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg transition-opacity" :class="page.url.startsWith('/robo-whatsapp') ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'">📱</span> 
                            <span class="text-sm font-medium">Robô WhatsApp</span>
                        </Link>
                        
                        <Link href="/portal-parceiro" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/portal-parceiro') ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg transition-opacity" :class="page.url.startsWith('/portal-parceiro') ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'">🤝</span> 
                            <span class="text-sm font-medium">Portal do Parceiro</span>
                        </Link>
                        
                        <Link href="/conciliacao-bancaria" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors group" :class="page.url.startsWith('/conciliacao-bancaria') ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-white hover:bg-slate-800'">
                            <span class="text-lg transition-opacity" :class="page.url.startsWith('/conciliacao-bancaria') ? 'opacity-100' : 'opacity-70 group-hover:opacity-100'">🏦</span> 
                            <span class="text-sm font-medium">Conciliação Bancária</span>
                        </Link>
                    </div>
                </div>
            </nav>
            
            <div class="p-4 border-t border-slate-800 shrink-0">
                <div class="flex items-center gap-3 px-3 py-2 bg-slate-800/50 rounded-xl cursor-pointer hover:bg-slate-800 transition-colors border border-transparent hover:border-slate-700">
                    <div class="w-9 h-9 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400 font-bold text-sm">DF</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white truncate">Diretoria Financeira</p>
                        <p class="text-[10px] font-medium text-slate-400 truncate">admin@bwt.com.br</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            
            <header class="bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-[0_2px_10px_rgba(0,0,0,0.02)] z-30 h-20 flex-shrink-0 flex items-center justify-between px-4 sm:px-8 absolute top-0 left-0 right-0">
                
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="md:hidden p-2 -ml-2 text-slate-500 hover:text-blue-600 bg-slate-50 rounded-lg border border-slate-200 shadow-sm focus:outline-none transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    
                    <div>
                        <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <slot name="header-title">Dashboard</slot>
                        </h1>
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mt-0.5">
                            <slot name="header-subtitle">Inteligência Financeira</slot>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 sm:gap-6">
                    <slot name="header-actions"></slot>

                    <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>

                    <button class="relative p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-pulse"></span>
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto pt-20 bg-slate-50">
                <div class="p-4 sm:p-8 max-w-screen-2xl mx-auto min-h-full">
                    <slot />
                </div>
            </main>
            
        </div>
        
        <div v-if="sidebarOpen" @click="toggleSidebar" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 md:hidden transition-opacity"></div>
        
    </div>
</template>

<style>
/* Esconde a barra de rolagem mas mantém o scroll funcionando limpo */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>