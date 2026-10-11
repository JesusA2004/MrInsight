<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading2,
    Heading3,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Sparkles,
    Underline as UnderlineIcon,
    Video as VideoIcon,
    MessageCircleQuestion,
} from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import MediaPickerDialog, {
    type PickedMedia,
} from '@/components/admin/MediaPickerDialog.vue';
import { Toggle } from '@/components/ui/toggle';
import { createPostExtensions } from '@/lib/tiptap/extensions';
import type { TiptapContent } from '@/types';

const content = defineModel<TiptapContent | null>('content', { default: null });

const editor = useEditor({
    content: content.value ?? '<p></p>',
    extensions: createPostExtensions(true),
    editorProps: {
        attributes: { class: 'prose-editor min-h-[320px] focus:outline-none' },
    },
    onUpdate: ({ editor: e }) => {
        content.value = e.getJSON();
    },
});

watch(content, (value) => {
    if (!editor.value) return;
    const current = JSON.stringify(editor.value.getJSON());
    const incoming = JSON.stringify(value);
    if (current !== incoming) {
        editor.value.commands.setContent(value ?? '<p></p>', {
            emitUpdate: false,
        });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const imagePickerOpen = ref(false);
const videoPickerOpen = ref(false);

function insertLink() {
    const url = window.prompt('URL del enlace');
    if (!url) return;
    editor.value?.chain().focus().setLink({ href: url }).run();
}

function onImageSelected(media: PickedMedia) {
    if (!media.url) return;
    editor.value
        ?.chain()
        .focus()
        .setImage({ src: media.url, alt: media.altText ?? media.filename })
        .run();
}

function onVideoSelected(media: PickedMedia) {
    if (!media.url) return;
    editor.value?.commands.insertVideoBlock({
        mediaId: media.id,
        src: media.url,
        poster: null,
    });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 rounded-lg border bg-muted/40 p-1.5"
        >
            <Toggle
                size="sm"
                :pressed="editor.isActive('bold')"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('italic')"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('underline')"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('heading', { level: 2 })"
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                <Heading2 class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('heading', { level: 3 })"
                @click="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
            >
                <Heading3 class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('bulletList')"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('orderedList')"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('blockquote')"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="false"
                @click="editor.chain().focus().setHorizontalRule().run()"
            >
                <Minus class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="editor.isActive('link')"
                @click="insertLink"
            >
                <Link2 class="size-4" />
            </Toggle>
            <Toggle size="sm" :pressed="false" @click="imagePickerOpen = true">
                <ImageIcon class="size-4" />
            </Toggle>
            <Toggle size="sm" :pressed="false" @click="videoPickerOpen = true">
                <VideoIcon class="size-4" />
            </Toggle>
            <Toggle
                size="sm"
                :pressed="false"
                @click="editor.commands.insertTipBlock()"
            >
                <Sparkles class="size-4" />
                EL TIP
            </Toggle>
            <Toggle
                size="sm"
                :pressed="false"
                @click="editor.commands.insertQuestionBlock()"
            >
                <MessageCircleQuestion class="size-4" />
                Pregunta final
            </Toggle>
        </div>

        <EditorContent :editor="editor" class="rounded-lg border px-4 py-3" />

        <MediaPickerDialog
            v-model:open="imagePickerOpen"
            type="image"
            @select="onImageSelected"
        />
        <MediaPickerDialog
            v-model:open="videoPickerOpen"
            type="video"
            @select="onVideoSelected"
        />
    </div>
</template>

<style>
.prose-editor :is(h2, h3, h4) {
    font-family: var(--font-display, serif);
    font-weight: 600;
    margin-top: 1.25em;
}
.prose-editor p {
    margin: 0.75em 0;
    line-height: 1.6;
}
.prose-editor ul,
.prose-editor ol {
    padding-left: 1.5em;
    margin: 0.75em 0;
}
.prose-editor blockquote {
    border-left: 3px solid var(--color-gold, #c7aa7d);
    padding-left: 1em;
    font-style: italic;
    color: var(--color-mist, #6b7280);
}
.prose-editor .tip-block {
    border-left: 4px solid var(--color-gold, #c7aa7d);
    background: color-mix(in srgb, var(--color-gold, #c7aa7d) 10%, transparent);
    border-radius: 0.5rem;
    padding: 1rem 1.25rem;
    margin: 1.25em 0;
}
.prose-editor .tip-block::before {
    content: 'EL TIP';
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: var(--color-gold, #c7aa7d);
    margin-bottom: 0.4em;
}
.prose-editor .question-block {
    border: 1px dashed var(--color-gold, #c7aa7d);
    border-radius: 0.5rem;
    padding: 1rem 1.25rem;
    margin: 1.25em 0;
    font-family: var(--font-display, serif);
    font-size: 1.1rem;
}
.prose-editor img {
    border-radius: 0.5rem;
    max-width: 100%;
}
</style>
