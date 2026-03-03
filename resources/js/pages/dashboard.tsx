import autoAnimate from '@formkit/auto-animate';
import type { AutoAnimationPlugin } from '@formkit/auto-animate';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEchoPresence, useEchoPublic } from '@laravel/echo-react';
import { ChevronRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { store as storeChatMessage } from '@/actions/App/Http/Controllers/Dashboard/ChatMessageController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { teamChat } from '@/routes';
import type { Auth, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Team Chat',
        href: teamChat(),
    },
];

const staggerUpAnimation: AutoAnimationPlugin = (
    element,
    action,
    newCoordinates,
    oldCoordinates,
) => {
    const siblings = element.parentElement
        ? Array.from(element.parentElement.children)
        : [];
    const reverseIndex = Math.max(
        siblings.length - 1 - siblings.indexOf(element),
        0,
    );

    if (action === 'add') {
        return new KeyframeEffect(
            element,
            [
                { opacity: 0, transform: 'translateY(10px)' },
                { opacity: 1, transform: 'translateY(0)' },
            ],
            {
                duration: 260,
                delay: Math.min(reverseIndex * 35, 280),
                easing: 'ease-out',
                fill: 'both',
            },
        );
    }

    const deltaX =
        oldCoordinates !== undefined && newCoordinates !== undefined
            ? oldCoordinates.left - newCoordinates.left
            : 0;
    const deltaY =
        oldCoordinates !== undefined && newCoordinates !== undefined
            ? oldCoordinates.top - newCoordinates.top
            : 0;

    return new KeyframeEffect(
        element,
        [
            { transform: `translate(${deltaX}px, ${deltaY}px)` },
            { transform: 'translate(0, 0)' },
        ],
        {
            duration: 220,
            delay: Math.min(reverseIndex * 20, 160),
            easing: 'ease-in-out',
        },
    );
};

export default function Dashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            id: 'welcome',
            kind: 'simple',
            user_id: 0,
            user_name: 'System',
            body: 'Welcome to the dashboard chat. Use /bot to ask the assistant.',
            sent_at: new Date().toISOString(),
        },
    ]);
    const form = useForm({
        message: '',
    });
    const messageFormRef = useRef<HTMLFormElement | null>(null);
    const messageListElementRef = useRef<HTMLDivElement | null>(null);
    const [onlineUsers, setOnlineUsers] = useState<OnlineUser[]>([]);

    const activeBotMessageId = useRef<string | null>(null);
    const pendingLocalBotMessageIds = useRef<string[]>([]);
    const botMessageIdsByInvocation = useRef<Record<string, string>>({});

    const updateBotMessageById = (
        id: string,
        updater: (message: BotChatMessage) => BotChatMessage,
    ): void => {
        setMessages((currentMessages) =>
            currentMessages.map((message) => {
                if (message.id !== id || message.kind !== 'bot') {
                    return message;
                }

                return updater(message);
            }),
        );
    };

    const createRemoteBotMessage = (invocationId: string): string => {
        const messageId = `bot-invocation-${invocationId}`;

        setMessages((currentMessages) => [
            ...currentMessages,
            {
                id: messageId,
                kind: 'bot',
                user_id: 0,
                user_name: 'Bot',
                body: '',
                sent_at: new Date().toISOString(),
                thinking: '',
                isThinking: false,
                hasThinking: false,
                showThinking: false,
            },
        ]);

        return messageId;
    };

    const applyBotChunk = (event: BotStreamEvent): void => {
        let targetMessageId: string | null = null;

        if (typeof event.invocation_id === 'string') {
            const existing =
                botMessageIdsByInvocation.current[event.invocation_id];

            if (existing !== undefined) {
                targetMessageId = existing;
            } else {
                const nextPendingLocal =
                    pendingLocalBotMessageIds.current.shift();

                if (nextPendingLocal !== undefined) {
                    targetMessageId = nextPendingLocal;
                } else {
                    targetMessageId = createRemoteBotMessage(
                        event.invocation_id,
                    );
                }

                botMessageIdsByInvocation.current[event.invocation_id] =
                    targetMessageId;
            }
        } else {
            targetMessageId = activeBotMessageId.current;
        }

        if (targetMessageId === null) {
            return;
        }

        if (event.type === 'reasoning_start') {
            updateBotMessageById(targetMessageId, (message) => ({
                ...message,
                isThinking: true,
                hasThinking: true,
            }));
        }

        if (
            event.type === 'reasoning_delta' &&
            typeof event.delta === 'string'
        ) {
            updateBotMessageById(targetMessageId, (message) => ({
                ...message,
                hasThinking: true,
                thinking: message.thinking + event.delta,
            }));
        }

        if (event.type === 'reasoning_end' || event.type === 'stream_end') {
            updateBotMessageById(targetMessageId, (message) => ({
                ...message,
                isThinking: false,
            }));
        }

        if (event.type === 'text_delta' && typeof event.delta === 'string') {
            updateBotMessageById(targetMessageId, (message) => ({
                ...message,
                body: message.body + event.delta,
            }));
        }
    };

    const parseBotStreamEvent = (
        payload: unknown,
        fallbackType: BotStreamEvent['type'],
    ): BotStreamEvent | null => {
        if (payload === null || payload === undefined) {
            return null;
        }

        if (typeof payload === 'string') {
            try {
                const parsed = JSON.parse(payload) as Partial<BotStreamEvent>;

                return {
                    type: parsed.type ?? fallbackType,
                    delta: parsed.delta,
                    invocation_id: parsed.invocation_id,
                };
            } catch {
                return null;
            }
        }

        if (typeof payload === 'object') {
            const raw = payload as {
                type?: string;
                delta?: string;
                invocation_id?: string;
                data?: unknown;
                event?: string;
            };

            if (typeof raw.type === 'string') {
                return {
                    type: raw.type,
                    delta: raw.delta,
                    invocation_id: raw.invocation_id,
                };
            }

            if (raw.data !== undefined) {
                return parseBotStreamEvent(raw.data, raw.event ?? fallbackType);
            }
        }

        return null;
    };

    useEchoPublic<BroadcastPayload>(
        'dashboard-chat',
        'Dashboard.ChatMessageSent',
        (event) => {
            const body = event.message.body.trim();

            if (body === '') {
                return;
            }

            setMessages((currentMessages) => [
                ...currentMessages.filter(
                    (message) => message.id !== event.message.id,
                ),
                {
                    ...event.message,
                    body,
                    kind: 'simple',
                },
            ]);
        },
    );

    useEchoPublic<unknown>(
        'dashboard-chat',
        [
            '.stream_start',
            '.reasoning_start',
            '.reasoning_delta',
            '.reasoning_end',
            '.text_delta',
            '.stream_end',
        ],
        (payload) => {
            const event = parseBotStreamEvent(payload, 'text_delta');

            if (event !== null) {
                applyBotChunk(event);
            }
        },
    );

    const presence = useEchoPresence<OnlineUser>('dashboard-online');

    useEffect(() => {
        const channel = presence.channel();

        channel.here((users: OnlineUser[]) => {
            setOnlineUsers(users);
        });

        channel.joining((user: OnlineUser) => {
            setOnlineUsers((currentUsers) => {
                if (
                    currentUsers.some(
                        (currentUser) => currentUser.id === user.id,
                    )
                ) {
                    return currentUsers;
                }

                return [...currentUsers, user];
            });
        });

        channel.leaving((user: OnlineUser) => {
            setOnlineUsers((currentUsers) =>
                currentUsers.filter(
                    (currentUser) => currentUser.id !== user.id,
                ),
            );
        });
    }, [presence]);

    const bindMessageList = (element: HTMLDivElement | null): void => {
        if (element === null || messageListElementRef.current === element) {
            return;
        }

        messageListElementRef.current = element;

        autoAnimate(element, staggerUpAnimation);
    };

    useEffect(() => {
        if (messageListElementRef.current === null) {
            return;
        }

        messageListElementRef.current.scrollTop =
            messageListElementRef.current.scrollHeight;
    }, [messages]);

    const sortedMessages = useMemo(
        () =>
            [...messages]
                .filter(
                    (message) =>
                        message.kind === 'bot' || message.body.trim() !== '',
                )
                .sort(
                    (a, b) =>
                        new Date(a.sent_at).getTime() -
                        new Date(b.sent_at).getTime(),
                ),
        [messages],
    );

    const sendOutgoingMessage = (value: string): void => {
        const rawMessage = value.trim();

        if (rawMessage === '') {
            form.setError('message', 'Please enter a message before sending.');
            return;
        }

        if (/^(\/bot|@[a-z0-9_-]+)\b/i.test(rawMessage)) {
            const prompt = rawMessage
                .replace(/^(\/bot|@[a-z0-9_-]+)\b\s*/i, '')
                .trim();

            if (prompt === '') {
                form.setError(
                    'message',
                    'Use /bot or @bot followed by a prompt.',
                );
                return;
            }

            const visibleCommandMessage = rawMessage
                .replace(/^\/bot\b\s*/i, '@bot ')
                .trim();

            const now = new Date();
            const clientMessageId = `msg-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
            const userMessage: SimpleChatMessage = {
                id: clientMessageId,
                kind: 'simple',
                user_id: auth.user.id,
                user_name: auth.user.name,
                body: visibleCommandMessage,
                sent_at: now.toISOString(),
            };

            const botMessageId = `local-bot-${now.getTime() + 1}`;
            const botMessage: BotChatMessage = {
                id: botMessageId,
                kind: 'bot',
                user_id: 0,
                user_name: 'Bot',
                body: '',
                sent_at: new Date(now.getTime() + 1).toISOString(),
                thinking: '',
                isThinking: true,
                hasThinking: false,
                showThinking: false,
            };

            setMessages((currentMessages) => [
                ...currentMessages,
                userMessage,
                botMessage,
            ]);

            activeBotMessageId.current = botMessageId;
            pendingLocalBotMessageIds.current.push(botMessageId);

            router.post(
                storeChatMessage.url(),
                {
                    message: rawMessage,
                    client_message_id: clientMessageId,
                },
                {
                    preserveScroll: true,
                    onError: (errors) => {
                        updateBotMessageById(botMessageId, (message) => ({
                            ...message,
                            isThinking: false,
                            body:
                                typeof errors.message === 'string'
                                    ? errors.message
                                    : 'Bot request failed.',
                        }));
                    },
                },
            );

            form.reset('message');
            form.clearErrors('message');

            return;
        }

        router.post(
            storeChatMessage.url(),
            { message: rawMessage },
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset('message');
                    form.clearErrors('message');
                },
                onError: (errors) => {
                    form.setError(
                        'message',
                        typeof errors.message === 'string'
                            ? errors.message
                            : 'Please enter a message before sending.',
                    );
                },
            },
        );
    };

    const sendMessage = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        sendOutgoingMessage(form.data.message);
    };

    const sendFireSpam = (): void => {
        sendOutgoingMessage('🔥');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team Chat" />
            <div className="flex min-h-0 w-full flex-1 flex-col overflow-hidden p-4">
                <div className="mb-3 shrink-0">
                    <h1 className="text-lg font-semibold">Team chat</h1>
                    <p className="text-sm text-muted-foreground">
                        Send regular messages, or mention a bot like{' '}
                        <code>@bot</code>, <code>@jimmy</code>,
                        <code> @micheal</code>, <code>@dwight</code>, or{' '}
                        <code>@financial</code>.
                    </p>
                </div>

                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border bg-background lg:flex-row">
                    <div className="relative min-h-0 min-w-0 flex-1">
                        <div className="absolute inset-0 flex flex-col overflow-hidden">
                            <div
                                ref={bindMessageList}
                                className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-4"
                            >
                                {sortedMessages.map((message) => {
                                    const ownMessage =
                                        message.user_id === auth.user.id;

                                    return (
                                        <div
                                            key={message.id}
                                            className={cn(
                                                'flex',
                                                ownMessage
                                                    ? 'justify-end'
                                                    : 'justify-start',
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    'max-w-[85%] rounded-lg px-4 py-2 text-sm',
                                                    ownMessage
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-foreground',
                                                    message.kind === 'bot' &&
                                                        'border border-emerald-500/40 bg-emerald-500/10',
                                                )}
                                            >
                                                <p className="mb-1 text-xs font-medium opacity-80">
                                                    {message.user_name}
                                                </p>

                                                {message.kind === 'bot' &&
                                                    message.hasThinking && (
                                                        <Collapsible
                                                            open={
                                                                message.showThinking
                                                            }
                                                            onOpenChange={(
                                                                open,
                                                            ) => {
                                                                setMessages(
                                                                    (
                                                                        currentMessages,
                                                                    ) =>
                                                                        currentMessages.map(
                                                                            (
                                                                                currentMessage,
                                                                            ) => {
                                                                                if (
                                                                                    currentMessage.id !==
                                                                                        message.id ||
                                                                                    currentMessage.kind !==
                                                                                        'bot'
                                                                                ) {
                                                                                    return currentMessage;
                                                                                }

                                                                                return {
                                                                                    ...currentMessage,
                                                                                    showThinking:
                                                                                        open,
                                                                                };
                                                                            },
                                                                        ),
                                                                );
                                                            }}
                                                            className="mb-2 rounded-md border border-foreground/20"
                                                        >
                                                            <CollapsibleTrigger className="flex w-full items-center justify-between px-2 py-1.5 text-xs">
                                                                <span className="flex items-center gap-1.5">
                                                                    {message.isThinking && (
                                                                        <Spinner className="size-3" />
                                                                    )}
                                                                    {message.isThinking
                                                                        ? 'Thinking...'
                                                                        : 'Show thinking'}
                                                                </span>
                                                                <ChevronRight
                                                                    className={cn(
                                                                        'size-3 transition-transform',
                                                                        message.showThinking &&
                                                                            'rotate-90',
                                                                    )}
                                                                />
                                                            </CollapsibleTrigger>
                                                            <CollapsibleContent className="border-t border-foreground/20 px-2 py-1.5">
                                                                <div className="max-h-40 overflow-y-auto text-xs whitespace-pre-wrap opacity-90">
                                                                    {
                                                                        message.thinking
                                                                    }
                                                                </div>
                                                            </CollapsibleContent>
                                                        </Collapsible>
                                                    )}

                                                {message.body !== '' ? (
                                                    message.kind === 'bot' ? (
                                                        <ReactMarkdown
                                                            remarkPlugins={[
                                                                remarkGfm,
                                                            ]}
                                                            components={{
                                                                p: ({
                                                                    children,
                                                                }) => (
                                                                    <p className="mb-2 last:mb-0">
                                                                        {
                                                                            children
                                                                        }
                                                                    </p>
                                                                ),
                                                                ul: ({
                                                                    children,
                                                                }) => (
                                                                    <ul className="mb-2 list-disc pl-5 last:mb-0">
                                                                        {
                                                                            children
                                                                        }
                                                                    </ul>
                                                                ),
                                                                ol: ({
                                                                    children,
                                                                }) => (
                                                                    <ol className="mb-2 list-decimal pl-5 last:mb-0">
                                                                        {
                                                                            children
                                                                        }
                                                                    </ol>
                                                                ),
                                                                li: ({
                                                                    children,
                                                                }) => (
                                                                    <li className="mb-1 last:mb-0">
                                                                        {
                                                                            children
                                                                        }
                                                                    </li>
                                                                ),
                                                                code: ({
                                                                    children,
                                                                }) => (
                                                                    <code className="rounded bg-black/10 px-1 py-0.5 text-xs">
                                                                        {
                                                                            children
                                                                        }
                                                                    </code>
                                                                ),
                                                                pre: ({
                                                                    children,
                                                                }) => (
                                                                    <pre className="mb-2 overflow-x-auto rounded bg-black/10 p-2 text-xs">
                                                                        {
                                                                            children
                                                                        }
                                                                    </pre>
                                                                ),
                                                            }}
                                                        >
                                                            {message.body}
                                                        </ReactMarkdown>
                                                    ) : (
                                                        <p>{message.body}</p>
                                                    )
                                                ) : message.kind === 'bot' ? (
                                                    <p className="inline-flex items-center opacity-80">
                                                        <Spinner className="size-3" />
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <form
                                ref={messageFormRef}
                                onSubmit={sendMessage}
                                className="shrink-0 border-t p-3"
                            >
                                <div className="flex gap-2">
                                    <Input
                                        value={form.data.message}
                                        onChange={(event) =>
                                            form.setData(
                                                'message',
                                                event.target.value,
                                            )
                                        }
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' &&
                                                !event.shiftKey
                                            ) {
                                                event.preventDefault();
                                                messageFormRef.current?.requestSubmit();
                                            }
                                        }}
                                        placeholder="Write a message or mention @bot/@jimmy/@micheal/@dwight/@financial..."
                                        maxLength={1000}
                                    />
                                    <Button
                                        type="button"
                                        disabled={form.processing}
                                        onClick={sendFireSpam}
                                    >
                                        🔥
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        Send
                                    </Button>
                                </div>
                                <InputError message={form.errors.message} />
                            </form>
                        </div>
                    </div>

                    <aside className="w-full border-t bg-muted/30 p-4 lg:w-72 lg:border-t-0 lg:border-l">
                        <h2 className="mb-3 text-sm font-semibold">
                            Online users ({onlineUsers.length})
                        </h2>
                        <div className="space-y-2">
                            {onlineUsers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No one online yet.
                                </p>
                            ) : (
                                onlineUsers.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center gap-2 rounded-md bg-background px-2 py-1.5 text-sm"
                                    >
                                        <span className="size-2 rounded-full bg-emerald-500" />
                                        <span>{user.name}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

type SimpleChatMessage = {
    id: string;
    kind: 'simple';
    user_id: number;
    user_name: string;
    body: string;
    sent_at: string;
};

type BotChatMessage = {
    id: string;
    kind: 'bot';
    user_id: number;
    user_name: string;
    body: string;
    sent_at: string;
    thinking: string;
    isThinking: boolean;
    hasThinking: boolean;
    showThinking: boolean;
};

type ChatMessage = SimpleChatMessage | BotChatMessage;

type BroadcastPayload = {
    message: {
        id: string;
        user_id: number;
        user_name: string;
        body: string;
        sent_at: string;
    };
};

type BotStreamEvent = {
    type: string;
    delta?: string;
    invocation_id?: string;
};

type OnlineUser = {
    id: number;
    name: string;
};
