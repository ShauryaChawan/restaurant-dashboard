"use client";

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';

export default function PeakHourChart({ data = [] }: { data?: any[] }) {
  const maxCount = Math.max(...data.map((d) => d.order_count), 0);

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">Peak Order Hour per Day</h3>
      {data.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-8">No data for this range</p>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <BarChart data={data} margin={{ top: 5, right: 10, left: -10, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis
              dataKey="date"
              tick={{ fontSize: 11, fill: '#6b7280' }}
              tickFormatter={(d) => d.slice(5)}
            />
            <YAxis
              domain={[0, 23]}
              tick={{ fontSize: 11, fill: '#6b7280' }}
              tickFormatter={(h) => `${h}:00`}
            />
            <Tooltip
              contentStyle={{ fontSize: 12, borderRadius: '8px', border: '1px solid #e5e7eb' }}
              labelFormatter={(d) => `Date: ${d}`}
              formatter={(v: any, _n: any, props: any) => [
                `${v}:00  (${props.payload.order_count} orders)`,
                'Peak Hour',
              ]}
            />
            <Bar dataKey="peak_hour" radius={[4, 4, 0, 0]}>
              {data.map((entry, idx) => (
                <Cell key={idx} fill={entry.order_count === maxCount ? '#111827' : '#d1d5db'} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
