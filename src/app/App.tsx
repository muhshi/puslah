import { useState } from 'react';
import { Trophy, Moon, Sun, Award } from 'lucide-react';

export default function App() {
  const [isDark, setIsDark] = useState(true);

  const topUsers = [
    { rank: 2, name: 'Mark Ruffalo', xp: '12,345', avatar: '👨' },
    { rank: 1, name: 'Chris Evans', xp: '15,420', avatar: '👨‍🦰' },
    { rank: 3, name: 'Scarlett Johansson', xp: '10,890', avatar: '👩' }
  ];

  const leaderboardData = [
    { rank: 4, name: 'Robert Downey Jr.', totalXp: '9,234', todayXp: '156', tasks: 23 },
    { rank: 5, name: 'Chris Hemsworth', totalXp: '8,567', todayXp: '203', tasks: 19 },
    { rank: 6, name: 'Tom Holland', totalXp: '7,891', todayXp: '178', tasks: 21 },
    { rank: 7, name: 'Benedict', totalXp: '7,234', todayXp: '145', tasks: 18 },
    { rank: 8, name: 'Paul Rudd', totalXp: '6,890', todayXp: '167', tasks: 20 }
  ];

  const avatarColors = ['bg-orange-500', 'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500'];

  return (
    <div className={`min-h-screen ${isDark ? 'bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900' : 'bg-gradient-to-br from-slate-50 via-purple-50 to-slate-50'}`}>
      {/* Header */}
      <header className={`border-b ${isDark ? 'border-white/10 bg-slate-900/50' : 'border-slate-200 bg-white/50'} backdrop-blur-sm`}>
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Trophy className={isDark ? 'text-purple-400' : 'text-purple-600'} size={24} />
            <span className={`font-bold text-xl ${isDark ? 'text-white' : 'text-slate-900'}`}>Nexrise</span>
          </div>

          <nav className="flex items-center gap-8">
            <a href="#" className={`text-sm ${isDark ? 'text-purple-400' : 'text-purple-600'} font-medium`}>Leaderboards</a>
            <a href="#" className={`text-sm ${isDark ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'} transition`}>Dashboard</a>
            <a href="#" className={`text-sm ${isDark ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'} transition`}>Rewards</a>
            <a href="#" className={`text-sm ${isDark ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'} transition`}>Tools</a>
            <a href="#" className={`text-sm ${isDark ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'} transition`}>Discord</a>
          </nav>

          <button
            onClick={() => setIsDark(!isDark)}
            className={`p-2 rounded-lg ${isDark ? 'bg-white/10 hover:bg-white/20 text-white' : 'bg-slate-200 hover:bg-slate-300 text-slate-900'} transition`}
          >
            {isDark ? <Sun size={20} /> : <Moon size={20} />}
          </button>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-6 py-12">
        {/* Top 3 Podium */}
        <div className="mb-12">
          <h1 className={`text-3xl font-bold text-center mb-8 ${isDark ? 'text-white' : 'text-slate-900'}`}>
            Top Performers
          </h1>

          <div className="flex items-end justify-center gap-6 mb-6">
            {/* 2nd Place */}
            <div className={`flex flex-col items-center p-6 rounded-2xl ${isDark ? 'bg-gradient-to-b from-yellow-600/20 to-transparent border border-yellow-600/30' : 'bg-gradient-to-b from-yellow-200 to-transparent border border-yellow-400'} w-56`}>
              <div className="relative mb-4">
                <div className={`w-20 h-20 rounded-full ${avatarColors[0]} flex items-center justify-center text-3xl ${isDark ? 'ring-4 ring-yellow-600/50' : 'ring-4 ring-yellow-400'}`}>
                  {topUsers[0].avatar}
                </div>
                <div className={`absolute -bottom-2 left-1/2 -translate-x-1/2 w-8 h-8 rounded-full ${isDark ? 'bg-yellow-600' : 'bg-yellow-500'} flex items-center justify-center font-bold ${isDark ? 'text-white' : 'text-white'}`}>
                  2
                </div>
              </div>
              <h3 className={`font-semibold text-center mb-1 ${isDark ? 'text-white' : 'text-slate-900'}`}>{topUsers[0].name}</h3>
              <p className={`text-sm ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>{topUsers[0].xp} XP</p>
            </div>

            {/* 1st Place */}
            <div className={`flex flex-col items-center p-8 rounded-2xl ${isDark ? 'bg-gradient-to-b from-purple-600/30 to-transparent border border-purple-500/50' : 'bg-gradient-to-b from-purple-200 to-transparent border border-purple-400'} w-64 -mt-8`}>
              <Trophy className={`${isDark ? 'text-yellow-400' : 'text-yellow-600'} mb-2`} size={32} />
              <div className="relative mb-4">
                <div className={`w-24 h-24 rounded-full ${avatarColors[1]} flex items-center justify-center text-4xl ${isDark ? 'ring-4 ring-purple-500' : 'ring-4 ring-purple-400'}`}>
                  {topUsers[1].avatar}
                </div>
                <div className={`absolute -bottom-2 left-1/2 -translate-x-1/2 w-10 h-10 rounded-full ${isDark ? 'bg-purple-600' : 'bg-purple-500'} flex items-center justify-center font-bold text-white text-lg`}>
                  1
                </div>
              </div>
              <h3 className={`font-bold text-lg text-center mb-1 ${isDark ? 'text-white' : 'text-slate-900'}`}>{topUsers[1].name}</h3>
              <p className={`${isDark ? 'text-purple-300' : 'text-purple-600'} font-semibold`}>{topUsers[1].xp} XP</p>
            </div>

            {/* 3rd Place */}
            <div className={`flex flex-col items-center p-6 rounded-2xl ${isDark ? 'bg-gradient-to-b from-orange-600/20 to-transparent border border-orange-600/30' : 'bg-gradient-to-b from-orange-200 to-transparent border border-orange-400'} w-56`}>
              <div className="relative mb-4">
                <div className={`w-20 h-20 rounded-full ${avatarColors[2]} flex items-center justify-center text-3xl ${isDark ? 'ring-4 ring-orange-600/50' : 'ring-4 ring-orange-400'}`}>
                  {topUsers[2].avatar}
                </div>
                <div className={`absolute -bottom-2 left-1/2 -translate-x-1/2 w-8 h-8 rounded-full ${isDark ? 'bg-orange-600' : 'bg-orange-500'} flex items-center justify-center font-bold ${isDark ? 'text-white' : 'text-white'}`}>
                  3
                </div>
              </div>
              <h3 className={`font-semibold text-center mb-1 ${isDark ? 'text-white' : 'text-slate-900'}`}>{topUsers[2].name}</h3>
              <p className={`text-sm ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>{topUsers[2].xp} XP</p>
            </div>
          </div>

          <p className={`text-center ${isDark ? 'text-purple-300' : 'text-purple-600'} font-medium`}>
            It's your time to shine! ✨
          </p>
        </div>

        {/* Leaderboard Table */}
        <div className={`rounded-2xl overflow-hidden ${isDark ? 'bg-slate-800/50 border border-white/10' : 'bg-white border border-slate-200'} backdrop-blur-sm`}>
          <div className={`px-6 py-4 ${isDark ? 'bg-slate-800/80 border-b border-white/10' : 'bg-slate-50 border-b border-slate-200'}`}>
            <h2 className={`font-semibold ${isDark ? 'text-white' : 'text-slate-900'}`}>Leaderboard Rankings</h2>
            <p className={`text-sm ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Top performers ranked by total XP</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className={`${isDark ? 'bg-slate-800/50 text-slate-400' : 'bg-slate-50 text-slate-600'} text-sm`}>
                  <th className="px-6 py-3 text-left font-medium">Rank</th>
                  <th className="px-6 py-3 text-left font-medium">User</th>
                  <th className="px-6 py-3 text-right font-medium">Total XP</th>
                  <th className="px-6 py-3 text-right font-medium">XP Today</th>
                  <th className="px-6 py-3 text-right font-medium">Tasks</th>
                </tr>
              </thead>
              <tbody>
                {leaderboardData.map((user, index) => (
                  <tr
                    key={user.rank}
                    className={`${isDark ? 'border-t border-white/5 hover:bg-white/5' : 'border-t border-slate-100 hover:bg-slate-50'} transition`}
                  >
                    <td className="px-6 py-4">
                      <div className={`w-8 h-8 rounded-full ${isDark ? 'bg-slate-700' : 'bg-slate-200'} flex items-center justify-center font-semibold ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>
                        {user.rank}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 rounded-full ${avatarColors[index % avatarColors.length]} flex items-center justify-center text-xl`}>
                          {String.fromCodePoint(0x1F464 + index)}
                        </div>
                        <span className={`font-medium ${isDark ? 'text-white' : 'text-slate-900'}`}>{user.name}</span>
                      </div>
                    </td>
                    <td className={`px-6 py-4 text-right font-semibold ${isDark ? 'text-white' : 'text-slate-900'}`}>
                      {user.totalXp}
                    </td>
                    <td className={`px-6 py-4 text-right ${isDark ? 'text-purple-400' : 'text-purple-600'}`}>
                      +{user.todayXp}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <Award size={16} className={isDark ? 'text-slate-400' : 'text-slate-500'} />
                        <span className={isDark ? 'text-slate-300' : 'text-slate-700'}>{user.tasks}</span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  );
}