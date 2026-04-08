import { Home, Search, Bell, Mail, Bookmark, Users, User, MoreHorizontal, Heart, MessageCircle, Repeat2, Share, Verified } from 'lucide-react';

function App() {
  const posts = [
    {
      id: 1,
      author: {
        name: 'Sarah Johnson',
        username: '@sarahjdev',
        avatar: 'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200',
        verified: true
      },
      content: 'Just shipped a new feature that improves performance by 40%! The team worked incredibly hard on this. Excited to see the impact 🚀',
      timestamp: '2h',
      likes: 234,
      retweets: 45,
      replies: 12,
      image: 'https://images.pexels.com/photos/1181271/pexels-photo-1181271.jpeg?auto=compress&cs=tinysrgb&w=800'
    },
    {
      id: 2,
      author: {
        name: 'Sarah Johnson',
        username: '@sarahjdev',
        avatar: 'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200',
        verified: true
      },
      content: 'Hot take: The best code is the code you don\'t write. Sometimes the simplest solution is the most elegant one.',
      timestamp: '5h',
      likes: 892,
      retweets: 156,
      replies: 43
    },
    {
      id: 3,
      author: {
        name: 'Sarah Johnson',
        username: '@sarahjdev',
        avatar: 'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200',
        verified: true
      },
      content: 'Starting the day with coffee and code. What are you working on today?',
      timestamp: '12h',
      likes: 445,
      retweets: 23,
      replies: 67,
      image: 'https://images.pexels.com/photos/2777898/pexels-photo-2777898.jpeg?auto=compress&cs=tinysrgb&w=800'
    },
    {
      id: 4,
      author: {
        name: 'Sarah Johnson',
        username: '@sarahjdev',
        avatar: 'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200',
        verified: true
      },
      content: 'Amazing collaboration with the design team today. When developers and designers work closely together, magic happens ✨',
      timestamp: '1d',
      likes: 678,
      retweets: 89,
      replies: 34
    }
  ];

  return (
    <div className="min-h-screen bg-black text-white">
      <div className="max-w-[1280px] mx-auto flex">
        <aside className="w-[275px] h-screen sticky top-0 flex flex-col justify-between p-4 border-r border-gray-800">
          <div>
            <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-8 hover:bg-blue-600 transition-colors cursor-pointer">
              <svg viewBox="0 0 24 24" className="w-7 h-7 fill-white">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
            </div>

            <nav className="space-y-2">
              <NavItem icon={<Home size={26} />} label="Home" />
              <NavItem icon={<Search size={26} />} label="Explore" />
              <NavItem icon={<Bell size={26} />} label="Notifications" />
              <NavItem icon={<Mail size={26} />} label="Messages" />
              <NavItem icon={<Bookmark size={26} />} label="Bookmarks" />
              <NavItem icon={<Users size={26} />} label="Communities" />
              <NavItem icon={<User size={26} />} label="Profile" active />
            </nav>

            <button className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-full mt-4 transition-colors">
              Post
            </button>
          </div>

          <div className="flex items-center justify-between p-3 hover:bg-gray-900 rounded-full cursor-pointer transition-colors">
            <div className="flex items-center gap-3">
              <img
                src="https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200"
                alt="User"
                className="w-10 h-10 rounded-full"
              />
              <div className="flex-1">
                <div className="font-semibold text-sm">Sarah Johnson</div>
                <div className="text-gray-500 text-sm">@sarahjdev</div>
              </div>
            </div>
            <MoreHorizontal size={20} />
          </div>
        </aside>

        <main className="flex-1 border-r border-gray-800 max-w-[600px]">
          <div className="sticky top-0 backdrop-blur-md bg-black/80 z-10">
            <div className="p-4">
              <h1 className="text-xl font-bold">Sarah Johnson</h1>
              <p className="text-sm text-gray-500">432 posts</p>
            </div>
          </div>

          <div className="relative">
            <div className="h-[200px] bg-gradient-to-r from-blue-600 to-cyan-500"></div>

            <div className="px-4">
              <div className="flex justify-between items-start -mt-16 mb-4">
                <img
                  src="https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200"
                  alt="Profile"
                  className="w-32 h-32 rounded-full border-4 border-black"
                />
                <button className="mt-16 px-6 py-2 border border-gray-600 rounded-full font-semibold hover:bg-gray-900 transition-colors">
                  Edit profile
                </button>
              </div>

              <div className="mb-4">
                <div className="flex items-center gap-1 mb-1">
                  <h2 className="text-xl font-bold">Sarah Johnson</h2>
                  <Verified size={20} className="fill-blue-500 text-blue-500" />
                </div>
                <p className="text-gray-500 mb-3">@sarahjdev</p>
                <p className="mb-3">Senior Software Engineer | Building the future of web | Coffee enthusiast | Open source contributor</p>

                <div className="flex gap-4 text-sm text-gray-500 mb-3">
                  <div className="flex items-center gap-1">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
                    </svg>
                    San Francisco, CA
                  </div>
                  <div className="flex items-center gap-1">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
                    </svg>
                    Joined March 2018
                  </div>
                </div>

                <div className="flex gap-4 text-sm">
                  <div>
                    <span className="font-semibold text-white">842</span>
                    <span className="text-gray-500 ml-1">Following</span>
                  </div>
                  <div>
                    <span className="font-semibold text-white">12.4K</span>
                    <span className="text-gray-500 ml-1">Followers</span>
                  </div>
                </div>
              </div>

              <div className="border-b border-gray-800">
                <div className="flex">
                  <Tab label="Posts" active />
                  <Tab label="Replies" />
                  <Tab label="Media" />
                  <Tab label="Likes" />
                </div>
              </div>
            </div>
          </div>

          <div>
            {posts.map((post) => (
              <Post key={post.id} post={post} />
            ))}
          </div>
        </main>

        <aside className="w-[350px] p-4 hidden lg:block">
          <div className="sticky top-0 space-y-4">
            <div className="bg-gray-900 rounded-2xl p-4">
              <h2 className="text-xl font-bold mb-4">Subscribe to Premium</h2>
              <p className="text-sm text-gray-400 mb-3">Subscribe to unlock new features and if eligible, receive a share of ads revenue.</p>
              <button className="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full transition-colors">
                Subscribe
              </button>
            </div>

            <div className="bg-gray-900 rounded-2xl p-4">
              <h2 className="text-xl font-bold mb-4">Who to follow</h2>
              <div className="space-y-4">
                <FollowSuggestion
                  name="Alex Chen"
                  username="@alexchen"
                  avatar="https://images.pexels.com/photos/2379004/pexels-photo-2379004.jpeg?auto=compress&cs=tinysrgb&w=200"
                />
                <FollowSuggestion
                  name="Maya Patel"
                  username="@mayatech"
                  avatar="https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=200"
                  verified
                />
                <FollowSuggestion
                  name="David Kim"
                  username="@davidk"
                  avatar="https://images.pexels.com/photos/1516680/pexels-photo-1516680.jpeg?auto=compress&cs=tinysrgb&w=200"
                />
              </div>
            </div>

            <div className="text-xs text-gray-500 flex flex-wrap gap-2">
              <a href="#" className="hover:underline">Terms of Service</a>
              <a href="#" className="hover:underline">Privacy Policy</a>
              <a href="#" className="hover:underline">Cookie Policy</a>
              <a href="#" className="hover:underline">Accessibility</a>
              <span>© 2026 X Corp.</span>
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}

function NavItem({ icon, label, active = false }: { icon: React.ReactNode; label: string; active?: boolean }) {
  return (
    <div className={`flex items-center gap-4 px-4 py-3 rounded-full cursor-pointer transition-colors ${
      active ? 'font-bold' : 'hover:bg-gray-900'
    }`}>
      {icon}
      <span className="text-xl">{label}</span>
    </div>
  );
}

function Tab({ label, active = false }: { label: string; active?: boolean }) {
  return (
    <div className={`flex-1 text-center py-4 cursor-pointer hover:bg-gray-900 transition-colors relative ${
      active ? 'font-semibold' : 'text-gray-500'
    }`}>
      {label}
      {active && <div className="absolute bottom-0 left-0 right-0 h-1 bg-blue-500 rounded-full"></div>}
    </div>
  );
}

function Post({ post }: { post: any }) {
  return (
    <article className="border-b border-gray-800 p-4 hover:bg-gray-900/50 transition-colors cursor-pointer">
      <div className="flex gap-3">
        <img
          src={post.author.avatar}
          alt={post.author.name}
          className="w-10 h-10 rounded-full flex-shrink-0"
        />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className="font-semibold hover:underline">{post.author.name}</span>
            {post.author.verified && <Verified size={16} className="fill-blue-500 text-blue-500 flex-shrink-0" />}
            <span className="text-gray-500">@{post.author.username.substring(1)}</span>
            <span className="text-gray-500">·</span>
            <span className="text-gray-500">{post.timestamp}</span>
          </div>

          <p className="mb-3 whitespace-pre-wrap">{post.content}</p>

          {post.image && (
            <img
              src={post.image}
              alt="Post content"
              className="rounded-2xl w-full mb-3 border border-gray-800"
            />
          )}

          <div className="flex justify-between max-w-md">
            <button className="flex items-center gap-2 text-gray-500 hover:text-blue-500 transition-colors group">
              <div className="p-2 rounded-full group-hover:bg-blue-500/10">
                <MessageCircle size={18} />
              </div>
              <span className="text-sm">{post.replies}</span>
            </button>

            <button className="flex items-center gap-2 text-gray-500 hover:text-green-500 transition-colors group">
              <div className="p-2 rounded-full group-hover:bg-green-500/10">
                <Repeat2 size={18} />
              </div>
              <span className="text-sm">{post.retweets}</span>
            </button>

            <button className="flex items-center gap-2 text-gray-500 hover:text-pink-500 transition-colors group">
              <div className="p-2 rounded-full group-hover:bg-pink-500/10">
                <Heart size={18} />
              </div>
              <span className="text-sm">{post.likes}</span>
            </button>

            <button className="flex items-center gap-2 text-gray-500 hover:text-blue-500 transition-colors group">
              <div className="p-2 rounded-full group-hover:bg-blue-500/10">
                <Share size={18} />
              </div>
            </button>
          </div>
        </div>
      </div>
    </article>
  );
}

function FollowSuggestion({ name, username, avatar, verified = false }: { name: string; username: string; avatar: string; verified?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-2">
        <img src={avatar} alt={name} className="w-10 h-10 rounded-full" />
        <div>
          <div className="flex items-center gap-1">
            <span className="font-semibold text-sm hover:underline cursor-pointer">{name}</span>
            {verified && <Verified size={14} className="fill-blue-500 text-blue-500" />}
          </div>
          <span className="text-gray-500 text-sm">{username}</span>
        </div>
      </div>
      <button className="bg-white text-black font-semibold py-1.5 px-4 rounded-full text-sm hover:bg-gray-200 transition-colors">
        Follow
      </button>
    </div>
  );
}

export default App;
