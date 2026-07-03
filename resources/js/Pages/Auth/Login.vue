<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// UX Avançada: Estado para mostrar/ocultar senha
const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Acesso Restrito - BWT Logística" />

    <div class="min-h-screen flex bg-slate-50 font-sans selection:bg-blue-500 selection:text-white">
        
        <div class="hidden lg:flex lg:w-[45%] relative items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-blue-900"></div>
            
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>
            <div class="absolute inset-0 bg-cover bg-center mix-blend-overlay opacity-20" style="background-image: url('/images/bg-logistica.jpg');"></div>
            
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>

            <div class="relative z-10 p-16 max-w-xl text-white flex flex-col justify-between h-full py-24">
                <div>
                    <div class="w-12 h-1.5 bg-blue-500 mb-8 rounded-full"></div>
                    <h1 class="text-4xl font-black mb-6 leading-tight tracking-tight">
                        Governança Logística <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Orientada a Dados</span>
                    </h1>
                    <p class="text-base text-slate-300 mb-8 font-light leading-relaxed">
                        Sistema central de auditoria, faturamento e análise de spread FTL. Desenvolvido para proteger o caixa e escalar a operação da BWT.
                    </p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-sm text-slate-400 font-medium">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Auditoria de CT-e em Lote
                    </div>
                    <div class="flex items-center gap-3 text-sm text-slate-400 font-medium">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Cálculo de TDE e Taxas
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-[55%] flex flex-col justify-center items-center p-6 sm:p-12 z-20 relative">
            
            <div class="w-full max-w-[420px] bg-white p-8 sm:p-10 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                
                <div class="mb-8">
                    <img src="/images/logo.png" alt="BWT Logística" class="h-10 object-contain mb-6" onerror="this.outerHTML='<div class=\'mb-6 text-2xl font-black tracking-tight text-slate-900\'>BWT <span class=\'text-blue-600\'>LOGÍSTICA</span></div>'" />
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Bem-vindo de volta</h2>
                    <p class="text-sm text-slate-500 mt-1">Insira suas credenciais para acessar o painel.</p>
                </div>

                <div v-if="status" class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-100 flex items-start gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-medium text-emerald-700">{{ status }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">E-mail Corporativo</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                            </div>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username"
                                placeholder="nome@bwtlogistica.com.br"
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 outline-none sm:text-sm"
                                :class="{'border-red-500 focus:border-red-500 focus:ring-red-500/10': form.errors.email}"
                            >
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-500 font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-semibold text-slate-700">Senha</label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                                Esqueceu a senha?
                            </Link>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <input 
                                id="password" 
                                :type="showPassword ? 'text' : 'password'" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-10 pr-12 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-300 outline-none sm:text-sm"
                                :class="{'border-red-500 focus:border-red-500 focus:ring-red-500/10': form.errors.password}"
                            >
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword" 
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors focus:outline-none"
                            >
                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-500 font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center pt-2">
                        <div class="flex items-center h-5">
                            <input 
                                id="remember" 
                                type="checkbox" 
                                v-model="form.remember"
                                class="w-4 h-4 text-blue-600 bg-slate-50 border-slate-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer transition-colors"
                            >
                        </div>
                        <label for="remember" class="ml-2 text-sm font-medium text-slate-600 cursor-pointer select-none">
                            Lembrar meu acesso
                        </label>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full mt-2 flex justify-center items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-3 px-4 rounded-xl shadow-[0_4px_14px_0_rgb(37,99,235,0.39)] transition-all duration-300 active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <template v-if="form.processing">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Autenticando...
                        </template>
                        <template v-else>
                            Acessar Painel
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </template>
                    </button>
                </form>
            </div>

            <div class="absolute bottom-8 text-center w-full lg:w-[55%]">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">&copy; 2026 BWT Logística. ioapps.</p>
            </div>
            
        </div>
    </div>
</template>