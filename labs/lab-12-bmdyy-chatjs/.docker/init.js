// Chat.JS MongoDB seed — AppSec Arena
var db = new Mongo().getDB("chatjs");

db.createCollection("users");
db.createCollection("messages");

db.users.insertMany([
    {_id: 1, username: 'anton',     password: '1f29f2d29f02f2608eb72d45625ba3a851eda1ee2be1bda22427a584b787c722'},
    {_id: 2, username: 'bmdyy',     password: 'fde0870a3adfef9a02a0f89dc36e6bcfbeade8a6667c837c93ee48a952402ec3'},
    {_id: 3, username: 'john',      password: '96d9632f363564cc3032521409cf22a852f2032eec099ed5967c0d000cec607a'},
    {_id: 4, username: 'darkrider', password: '5062a87945ddf4dbe938d03fe8977a4f361dee0bb457fa7d751f12843fec717a'},
    {_id: 5, username: 'nightbus',  password: '454c11029503e362055ac28b5a33a6702bd3bc124b9a5a75494843b9836d0223'},
    {_id: 6, username: 'swallow',   password: '7276e0a742cf9880d1cfbb43a905e2ff6769bf461843399cfecfca0d68daf1bd'},
]);

db.messages.insertMany([
    {author: 1, datetime: new Date(), text: 'Guns are for show. Knives are for pros'},
    {author: 2, datetime: new Date(), text: 'First you take a drink, then the drink takes a drink, then the drink takes you'},
    {author: 3, datetime: new Date(), text: 'You already know how I feel about you. So what are you going to do about it?'},
    {author: 5, datetime: new Date(), text: 'Fear and ignorance would gay-marry each other if they weren\'t both opposed to it'},
    {author: 4, datetime: new Date(), text: 'Go do that voodoo that you do so well!'},
    {author: 6, datetime: new Date(), text: 'When was the last time you climbed a tree?'},
    {author: 3, datetime: new Date(), text: 'Chili crab for breakfast! Why not?'},
    {author: 2, datetime: new Date(), text: 'I see so you think, can you also DO?'},
    {author: 1, datetime: new Date(), text: 'Think and wonder, wonder and think'},
    {author: 6, datetime: new Date(), text: 'Why is the alphabet in that order?'},
    {author: 4, datetime: new Date(), text: 'Every time I have my picture taken I get hungry'},
    {author: 5, datetime: new Date(), text: 'the one next to the other one'},
    {author: 3, datetime: new Date(), text: 'Welcome'},
    {author: 1, datetime: new Date(), text: 'Which planet in the solar system would you visit?'},
    {author: 2, datetime: new Date(), text: 'Have you locked yourself out of the house?'},
]);
