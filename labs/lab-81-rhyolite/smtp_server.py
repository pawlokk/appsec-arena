#!/usr/bin/env python3
import asyncio
import email
import logging
import mysql.connector
from aiosmtpd.controller import Controller
from aiosmtpd.handlers import AsyncMessage

logging.basicConfig(level=logging.WARNING)

DB = dict(host='127.0.0.1', user='mailuser', password='mailpass', database='maildb')


class MailHandler(AsyncMessage):
    async def handle_message(self, message):
        try:
            to_addr = ', '.join(str(a) for a in message['to'].split(',')) if message['to'] else ''
            from_addr = str(message['from']) if message['from'] else ''
            subject = str(message['subject'] or '')
            date_hdr = str(message['date'] or '')
            if message.is_multipart():
                body = ''
                for part in message.walk():
                    if part.get_content_type() == 'text/html':
                        body = part.get_payload(decode=True).decode('utf-8', errors='replace')
                        break
                    if part.get_content_type() == 'text/plain' and not body:
                        body = part.get_payload(decode=True).decode('utf-8', errors='replace')
            else:
                ct = message.get_content_type()
                body = message.get_payload(decode=True).decode('utf-8', errors='replace') if ct in ('text/html', 'text/plain') else ''

            conn = mysql.connector.connect(**DB)
            cur = conn.cursor()
            for rcpt in to_addr.replace(' ', '').split(','):
                if rcpt:
                    cur.execute(
                        'INSERT INTO messages (recipient_email, sender_email, subject, date_header, body) VALUES (%s,%s,%s,%s,%s)',
                        (rcpt, from_addr, subject, date_hdr, body)
                    )
            conn.commit()
            cur.close()
            conn.close()
        except Exception as e:
            logging.error('smtp handler error: %s', e)


def main():
    handler = MailHandler()
    controller = Controller(handler, hostname='0.0.0.0', port=25)
    controller.start()
    asyncio.get_event_loop().run_forever()


if __name__ == '__main__':
    main()
