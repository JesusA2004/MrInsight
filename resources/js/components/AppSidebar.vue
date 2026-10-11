<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, FolderOpen, Home, Image, LayoutGrid, Share2, Tags } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import publicaciones from '@/routes/admin/publicaciones';
import categorias from '@/routes/admin/categorias';
import multimedia from '@/routes/admin/multimedia';
import redesSociales from '@/routes/admin/redes-sociales';
import paginas from '@/routes/admin/paginas';
import inicio from '@/routes/admin/inicio';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Publicaciones',
        href: publicaciones.index(),
        icon: FolderOpen,
    },
    {
        title: 'Categorías',
        href: categorias.index(),
        icon: Tags,
    },
    {
        title: 'Multimedia',
        href: multimedia.index(),
        icon: Image,
    },
    {
        title: 'Inicio',
        href: inicio.edit(),
        icon: Home,
    },
    {
        title: 'Redes sociales',
        href: redesSociales.index(),
        icon: Share2,
    },
    {
        title: 'Páginas',
        href: paginas.index(),
        icon: FileText,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
