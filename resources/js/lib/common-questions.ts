/*
 * The questions a client can send with one press, from the Common Questions button
 * in the thread. Pure, with no imports, for tests/js.
 */
export const COMMON_QUESTIONS: readonly string[] = [
    'How long does an order take?',
    'Do you offer nationwide installation?',
    'How do I request a quotation?',
];

/**
 * Whether a thread offers them: to a client who can write here, and not while
 * editing a message or while the new-messages pill has that spot.
 */
export const showsCommonQuestions = ({
    isClient,
    composerMode,
    isEditing,
    unseen,
}: {
    isClient: boolean;
    composerMode: 'enabled' | 'locked';
    isEditing: boolean;
    unseen: number;
}): boolean =>
    isClient && composerMode === 'enabled' && !isEditing && unseen === 0;
