import { EditorView, basicSetup } from 'codemirror';
import { xml } from '@codemirror/lang-xml';
import { json } from '@codemirror/lang-json';
import { EditorState } from '@codemirror/state';

window.initConfigEditor = function (container, livewireComponent) {
    const state = EditorState.create({
        doc: '',
        extensions: [
            basicSetup,
            xml(),
            json(),
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    livewireComponent.set('xmlContent', update.state.doc.toString());
                }
            }),
        ],
    });

    const editor = new EditorView({
        state,
        parent: container,
    });

    return editor;
};

window.setConfigEditorContent = function (editor, content) {
    editor.dispatch({
        changes: {
            from: 0,
            to: editor.state.doc.length,
            insert: content,
        },
    });
};
