<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const alertMessage = computed(
    () => page.props.flash?.error || page.props.flash?.status || '',
);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Iniciar sesión | GESTCOM" />

    <main class="login-page">
        <div class="login-shell">
            <section class="brand-panel" aria-label="GESTCOM">
                <div class="brand-panel__decoration brand-panel__decoration--one"></div>
                <div class="brand-panel__decoration brand-panel__decoration--two"></div>

                <div class="brand-panel__content">
                    <div class="brand-panel__message">
                        <span class="eyebrow">PORTAL INSTITUCIONAL</span>
                        <h1>La gestión de tu equipo, en un solo lugar.</h1>
                        <p>
                            Accede a las herramientas y la información que necesitas
                            para coordinar las operaciones de tu organización.
                        </p>
                    </div>

                    <div class="brand-panel__note">
                        <span class="brand-panel__note-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M12 3.5 19 6v5.7c0 4.3-2.9 7.5-7 8.8-4.1-1.3-7-4.5-7-8.8V6l7-2.5Z"
                                    stroke="currentColor"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                />
                                <path
                                    d="m9 12 2 2 4-4"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                />
                            </svg>
                        </span>
                        <span>Acceso seguro para personal autorizado</span>
                    </div>
                </div>

                <span class="brand-panel__footer">GESTCOM <span>·</span> Gestión y control</span>
            </section>

            <section class="form-panel" aria-labelledby="login-title">
                <div class="form-panel__inner">
                    <div class="login-brand">
                        <img
                            src="/images/logo-gestcom.png"
                            alt="GESTCOM"
                            class="login-brand__logo"
                        >
                    </div>

                    <div class="form-heading">
                        <span class="form-heading__badge">BIENVENIDO</span>
                        <h2 id="login-title">Inicia sesión</h2>
                        <p>Ingresa tus credenciales para continuar al portal.</p>
                    </div>

                    <div v-if="alertMessage" class="session-alert" role="status">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <circle cx="10" cy="10" r="7.5" stroke="currentColor" />
                            <path
                                d="M10 6.5v4M10 13.5h.01"
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-width="1.6"
                            />
                        </svg>
                        <p>{{ alertMessage }}</p>
                    </div>

                    <form class="login-form" @submit.prevent="submit">
                        <div class="field-group">
                            <label for="email">Correo electrónico</label>
                            <div
                                class="input-wrap"
                                :class="{ 'input-wrap--invalid': form.errors.email }"
                            >
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <rect
                                        x="2.5"
                                        y="4"
                                        width="15"
                                        height="12"
                                        rx="2"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    />
                                    <path
                                        d="m3.5 5.5 6.5 5 6.5-5"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                    />
                                </svg>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="username"
                                    placeholder="nombre@organizacion.com"
                                    :aria-invalid="Boolean(form.errors.email)"
                                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                                    autofocus
                                    required
                                >
                            </div>
                            <p
                                v-if="form.errors.email"
                                id="email-error"
                                class="field-error"
                                role="alert"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="field-group">
                            <label for="password">Contraseña</label>
                            <div
                                class="input-wrap"
                                :class="{ 'input-wrap--invalid': form.errors.password }"
                            >
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <rect
                                        x="3.5"
                                        y="8.5"
                                        width="13"
                                        height="9"
                                        rx="2"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    />
                                    <path
                                        d="M6.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-width="1.5"
                                    />
                                    <circle cx="10" cy="12.8" r="1" fill="currentColor" />
                                </svg>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    name="password"
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                    :aria-invalid="Boolean(form.errors.password)"
                                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                                    required
                                >
                            </div>
                            <p
                                v-if="form.errors.password"
                                id="password-error"
                                class="field-error"
                                role="alert"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <label class="remember-option">
                            <input v-model="form.remember" type="checkbox" name="remember">
                            <span class="remember-option__checkbox" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none">
                                    <path
                                        d="m3.5 8 3 3 6-6"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                    />
                                </svg>
                            </span>
                            <span>Recordarme en este dispositivo</span>
                        </label>

                        <button class="submit-button" type="submit" :disabled="form.processing">
                            <svg
                                v-if="form.processing"
                                class="submit-button__spinner"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                    stroke="currentColor"
                                    stroke-opacity=".3"
                                    stroke-width="3"
                                />
                                <path
                                    d="M21 12a9 9 0 0 0-9-9"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                            <span>{{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}</span>
                            <svg
                                v-if="!form.processing"
                                class="submit-button__arrow"
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 10h12m-5-5 5 5-5 5"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                />
                            </svg>
                        </button>
                    </form>

                    <p class="form-footnote">
                        ¿Necesitas ayuda para ingresar?
                        <span>Contacta al administrador del sistema.</span>
                    </p>
                </div>

                <footer class="form-panel__footer">
                    <span>© {{ new Date().getFullYear() }} GESTCOM</span>
                    <span class="form-panel__footer-dot"></span>
                    <span>Portal de gestión institucional</span>
                </footer>
            </section>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    --marca: #6042A6;
    --marca_oscuro: #342052;
    --marca_claro: #EEE7FA;
    --fondo: #FAF9FC;
    --texto_principal: #24212D;
    --texto_secundario: #706A7A;
    --borde: #E5E0EB;
    --blanco: #FFFFFF;
    --vencida_fondo: #FAE3E3;
    --vencida_texto: #C62828;
    --advertencia_fondo: #FFF0CE;
    --advertencia_texto: #684A00;
    --advertencia_texto_secundario: #795300;

    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    background:
        radial-gradient(ellipse at 12% 8%, rgba(96, 66, 166, 0.045), transparent 34%),
        var(--fondo);
    color: var(--texto_principal);
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.login-shell {
    display: grid;
    width: min(1080px, 100%);
    min-height: 650px;
    grid-template-columns: 0.94fr 1.06fr;
    overflow: hidden;
    border: 1px solid var(--borde);
    border-radius: 22px;
    background: var(--blanco);
    box-shadow: 0 28px 80px rgba(52, 32, 82, 0.11), 0 3px 12px rgba(36, 33, 45, 0.035);
}

.brand-panel {
    position: relative;
    display: flex;
    min-height: 650px;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    padding: 44px 48px 32px;
    background: var(--marca_oscuro);
    color: var(--blanco);
    isolation: isolate;
}

.brand-panel::before,
.brand-panel::after {
    position: absolute;
    z-index: -1;
    border: 1px solid rgba(238, 231, 250, 0.12);
    border-radius: 50%;
    content: '';
}

.brand-panel::before {
    top: 16%;
    right: -210px;
    width: 470px;
    height: 470px;
}

.brand-panel::after {
    top: 22%;
    right: -145px;
    width: 340px;
    height: 340px;
}

.brand-panel__decoration {
    position: absolute;
    z-index: -1;
    border-radius: 50%;
    background: rgba(96, 66, 166, 0.28);
    filter: blur(1px);
}

.brand-panel__decoration--one {
    top: 42%;
    right: -130px;
    width: 260px;
    height: 260px;
}

.brand-panel__decoration--two {
    right: 12%;
    bottom: -180px;
    width: 300px;
    height: 300px;
    background: rgba(238, 231, 250, 0.065);
}

.brand-panel__content {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.brand-panel__message {
    max-width: 390px;
    margin-top: auto;
    margin-bottom: auto;
    padding: 78px 0 72px;
}

.eyebrow,
.form-heading__badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 750;
    letter-spacing: 0.15em;
}

.eyebrow {
    padding: 9px 12px;
    background: rgba(238, 231, 250, 0.12);
    color: var(--marca_claro);
}

.brand-panel__message h1 {
    margin: 24px 0 16px;
    font-size: clamp(32px, 3.6vw, 43px);
    font-weight: 650;
    letter-spacing: -0.045em;
    line-height: 1.13;
}

.brand-panel__message p {
    max-width: 350px;
    margin: 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
    line-height: 1.8;
}

.brand-panel__note {
    display: flex;
    align-items: center;
    gap: 11px;
    color: rgba(255, 255, 255, 0.82);
    font-size: 12px;
}

.brand-panel__note-icon {
    display: grid;
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    place-items: center;
    border: 1px solid rgba(238, 231, 250, 0.22);
    border-radius: 10px;
    color: var(--marca_claro);
}

.brand-panel__note-icon svg {
    width: 19px;
    height: 19px;
}

.brand-panel__footer {
    color: rgba(255, 255, 255, 0.48);
    font-size: 10px;
    letter-spacing: 0.08em;
}

.brand-panel__footer span {
    padding: 0 5px;
    color: var(--marca_claro);
}

.form-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 56px clamp(36px, 6vw, 76px) 25px;
    background: var(--blanco);
}

.form-panel__inner {
    width: 100%;
    max-width: 390px;
    margin: auto;
}

.login-brand {
    display: flex;
    justify-content: center;
    margin-bottom: 28px;
}

.login-brand__logo {
    display: block;
    width: 116px;
    height: 116px;
    border-radius: 12px;
    background: var(--blanco);
    object-fit: contain;
}

.form-heading {
    margin-bottom: 32px;
}

.form-heading__badge {
    padding: 8px 11px;
    background: var(--marca_claro);
    color: var(--marca);
    font-size: 9px;
}

.form-heading h2 {
    margin: 19px 0 8px;
    color: var(--texto_principal);
    font-size: 30px;
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1.2;
}

.form-heading p {
    margin: 0;
    color: var(--texto_secundario);
    font-size: 13px;
    line-height: 1.65;
}

.session-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: -12px 0 22px;
    padding: 12px 13px;
    border: 1px solid rgba(104, 74, 0, 0.12);
    border-radius: 10px;
    background: var(--advertencia_fondo);
    color: var(--advertencia_texto);
}

.session-alert svg {
    width: 17px;
    height: 17px;
    flex: 0 0 17px;
    margin-top: 1px;
}

.session-alert p {
    margin: 0;
    color: var(--advertencia_texto_secundario);
    font-size: 12px;
    line-height: 1.55;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 21px;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-group > label {
    color: var(--texto_principal);
    font-size: 12px;
    font-weight: 650;
}

.input-wrap {
    display: flex;
    min-height: 49px;
    align-items: center;
    gap: 11px;
    padding: 0 14px;
    border: 1px solid var(--borde);
    border-radius: 10px;
    background: var(--blanco);
    color: var(--texto_secundario);
    transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
}

.input-wrap:focus-within {
    border-color: var(--marca);
    box-shadow: 0 0 0 3px rgba(96, 66, 166, 0.13);
}

.input-wrap--invalid {
    border-color: var(--vencida_texto);
    background: var(--vencida_fondo);
}

.input-wrap--invalid:focus-within {
    border-color: var(--vencida_texto);
    box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.12);
}

.input-wrap > svg {
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
    color: var(--marca);
}

.input-wrap input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--texto_principal);
    font: inherit;
    font-size: 12px;
}

.input-wrap input::placeholder {
    color: #A49EAC;
    opacity: 1;
}

.input-wrap input:-webkit-autofill,
.input-wrap input:-webkit-autofill:hover,
.input-wrap input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--texto_principal);
    box-shadow: 0 0 0 1000px var(--blanco) inset;
}

.field-error {
    margin: 0;
    padding: 8px 10px;
    border-radius: 7px;
    background: var(--vencida_fondo);
    color: var(--vencida_texto);
    font-size: 11px;
    line-height: 1.5;
}

.remember-option {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    gap: 9px;
    color: var(--texto_secundario);
    cursor: pointer;
    font-size: 11px;
    user-select: none;
}

.remember-option input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    clip-path: inset(50%);
}

.remember-option__checkbox {
    display: grid;
    width: 17px;
    height: 17px;
    flex: 0 0 17px;
    place-items: center;
    border: 1px solid var(--borde);
    border-radius: 5px;
    background: var(--blanco);
    color: var(--blanco);
    transition: background 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
}

.remember-option__checkbox svg {
    width: 13px;
    height: 13px;
    opacity: 0;
}

.remember-option input:checked + .remember-option__checkbox {
    border-color: var(--marca);
    background: var(--marca);
}

.remember-option input:checked + .remember-option__checkbox svg {
    opacity: 1;
}

.remember-option input:focus-visible + .remember-option__checkbox {
    outline: 3px solid rgba(96, 66, 166, 0.2);
    outline-offset: 2px;
}

.submit-button {
    display: flex;
    min-height: 50px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 2px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: var(--marca);
    color: var(--blanco);
    cursor: pointer;
    font: inherit;
    font-size: 13px;
    font-weight: 650;
    transition: background 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}

.submit-button:hover:not(:disabled) {
    transform: translateY(-1px);
    background: var(--marca_oscuro);
    box-shadow: 0 7px 17px rgba(52, 32, 82, 0.2);
}

.submit-button:focus-visible {
    outline: 3px solid rgba(96, 66, 166, 0.25);
    outline-offset: 3px;
}

.submit-button:disabled {
    cursor: wait;
    opacity: 0.75;
}

.submit-button__arrow {
    width: 17px;
    height: 17px;
    margin-left: auto;
}

.submit-button__spinner {
    width: 18px;
    height: 18px;
    animation: spin 800ms linear infinite;
}

.form-footnote {
    margin: 28px 0 0;
    color: var(--texto_secundario);
    font-size: 11px;
    line-height: 1.7;
    text-align: center;
}

.form-footnote span {
    display: block;
    color: var(--marca);
    font-weight: 600;
}

.form-panel__footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding-top: 34px;
    color: var(--texto_secundario);
    font-size: 9px;
}

.form-panel__footer-dot {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: var(--marca_claro);
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 760px) {
    .login-page {
        padding: 24px 16px;
    }

    .login-shell {
        width: min(480px, 100%);
        min-height: auto;
        grid-template-columns: 1fr;
        border-radius: 18px;
    }

    .brand-panel {
        display: none;
    }

    .form-panel {
        padding: 34px clamp(24px, 8vw, 42px) 22px;
    }

    .login-brand {
        margin-bottom: 24px;
    }

    .form-heading h2 {
        font-size: 28px;
    }
}

@media (max-width: 380px) {
    .login-page {
        padding: 12px;
    }

    .form-panel {
        padding-right: 22px;
        padding-left: 22px;
    }

    .login-brand__logo {
        width: 96px;
        height: 96px;
    }

    .form-panel__footer {
        flex-wrap: wrap;
        font-size: 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
