/**
 * A local SMTP sink for the browser run.
 *
 * The application under test sends invitation and password reset messages through its
 * ordinary SMTP transport. This sink accepts them on the loopback interface and writes each
 * one to the mailbox directory, where specs read the link a person would have clicked.
 * Nothing leaves the machine.
 */
import { mkdir, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { join } from 'node:path';
import { mailbox } from './environment.mjs';

let sequence = 0;

async function store(message) {
    sequence += 1;
    const name = `${String(Date.now()).padStart(15, '0')}-${String(sequence).padStart(6, '0')}.json`;
    await writeFile(join(mailbox.directory, name), JSON.stringify(message));
}

function handleConnection(socket) {
    let buffer = '';
    let reading = false;
    let envelope = { from: '', to: [] };
    const reply = (line) => socket.write(`${line}\r\n`);

    reply('220 refer-cheat-to-do e2e mailbox');
    socket.setEncoding('utf8');
    socket.on('error', () => socket.destroy());
    socket.on('data', (chunk) => {
        buffer += chunk;
        for (;;) {
            if (reading) {
                const end = buffer.indexOf('\r\n.\r\n');
                if (end === -1) {
                    return;
                }
                const data = buffer.slice(0, end).replace(/^\.\./gm, '.');
                buffer = buffer.slice(end + 5);
                reading = false;
                const message = { ...envelope, data };
                envelope = { from: '', to: [] };
                void store(message).then(() => reply('250 Stored'), () => reply('451 Could not store'));
                continue;
            }
            const end = buffer.indexOf('\r\n');
            if (end === -1) {
                return;
            }
            const line = buffer.slice(0, end);
            buffer = buffer.slice(end + 2);
            const command = line.slice(0, 4).toUpperCase();
            if (command === 'EHLO' || command === 'HELO') {
                reply('250 refer-cheat-to-do e2e mailbox');
            } else if (command === 'MAIL') {
                envelope.from = line.replace(/^MAIL FROM:\s*/i, '');
                reply('250 OK');
            } else if (command === 'RCPT') {
                envelope.to.push(line.replace(/^RCPT TO:\s*<?([^>]*)>?.*$/i, '$1').toLowerCase());
                reply('250 OK');
            } else if (command === 'DATA') {
                reading = true;
                reply('354 End data with <CR><LF>.<CR><LF>');
            } else if (command === 'QUIT') {
                reply('221 Bye');
                socket.end();
                return;
            } else {
                reply('250 OK');
            }
        }
    });
}

/**
 * Start the sink with an empty mailbox. Resolves to a function that stops it.
 */
export async function startMailbox() {
    await rm(mailbox.directory, { recursive: true, force: true });
    await mkdir(mailbox.directory, { recursive: true });
    const server = createServer(handleConnection);
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(mailbox.port, mailbox.host, resolve);
    });

    return () => new Promise((resolve) => {
        server.close(() => resolve());
    });
}

async function readMessages() {
    let names;
    try {
        names = (await readdir(mailbox.directory)).filter((name) => name.endsWith('.json')).sort();
    } catch {
        return [];
    }

    return Promise.all(names.map(async (name) => JSON.parse(await readFile(join(mailbox.directory, name), 'utf8'))));
}

/**
 * Number of messages delivered to an address so far, so a spec can wait for the next one.
 */
export async function countMessagesTo(address) {
    const recipient = address.toLowerCase();

    return (await readMessages()).filter((message) => message.to.includes(recipient)).length;
}

/**
 * Wait for the message after the first `previous` ones delivered to an address and return the
 * account link it carries, e.g. `{ kind: 'invite', token, url, data }`.
 */
export async function waitForAccountLink(address, previous = 0, timeout = 10_000) {
    const recipient = address.toLowerCase();
    const deadline = Date.now() + timeout;
    for (;;) {
        const messages = (await readMessages()).filter((message) => message.to.includes(recipient));
        if (messages.length > previous) {
            const { data } = messages[previous];
            const match = /(https?:\/\/\S+\/app#(invite|reset)=([A-Za-z0-9_-]{43}))(?=\s)/.exec(data);
            if (!match) {
                throw new Error(`The message to ${address} carries no account link.`);
            }

            return { url: match[1], kind: match[2], token: match[3], data };
        }
        if (Date.now() > deadline) {
            throw new Error(`No message reached ${address} within ${timeout} ms.`);
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
}
