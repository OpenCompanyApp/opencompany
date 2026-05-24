<script setup lang="ts">
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useWorkspace } from '@/composables/useWorkspace';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

const showingNavigationDropdown = ref(false);
const page = usePage();
const { workspaceRouteParams } = useWorkspace();
const authUser = (page.props as { auth: { user: { name: string; email: string } } }).auth.user;
const dashboardUrl = () => dashboard.url(workspaceRouteParams());
const profileUrl = () => editProfile.url(workspaceRouteParams());
const logoutUrl = () => logout.url();
const isDashboard = () => page.url === dashboard.url(workspaceRouteParams());
</script>

<template>
    <div>
        <div class="min-h-screen bg-neutral-100 dark:bg-neutral-700">
            <nav
                class="border-b border-neutral-100 dark:border-neutral-800 bg-white dark:bg-neutral-900"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="dashboardUrl()">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-neutral-800 dark:text-neutral-100"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="dashboardUrl()"
                                    :active="isDashboard()"
                                >
                                    Dashboard
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white dark:bg-neutral-900 px-3 py-2 text-sm font-medium leading-4 text-neutral-500 dark:text-neutral-300 transition duration-150 ease-in-out hover:text-neutral-700 dark:hover:text-neutral-200 focus:outline-none"
                                            >
                                                {{ authUser.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="profileUrl()"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="logoutUrl()"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-neutral-400 dark:text-neutral-400 transition duration-150 ease-in-out hover:bg-neutral-100 dark:hover:bg-neutral-700 hover:text-neutral-500 dark:hover:text-neutral-400 focus:bg-neutral-100 dark:focus:bg-neutral-700 focus:text-neutral-500 dark:focus:text-neutral-400 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="dashboardUrl()"
                            :active="isDashboard()"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-neutral-200 dark:border-neutral-700 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-neutral-800 dark:text-neutral-100"
                            >
                                {{ authUser.name }}
                            </div>
                            <div class="text-sm font-medium text-neutral-500 dark:text-neutral-300">
                                {{ authUser.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="profileUrl()">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="logoutUrl()"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white dark:bg-neutral-900 shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
