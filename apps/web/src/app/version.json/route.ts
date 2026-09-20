import { NextResponse } from 'next/server';

export async function GET() {
  return NextResponse.json({
    name: 'himoto-fleet-dashboard',
    version: '1.1.0',
    commit: '2b4911645cdeb388e43c8d3210d8eb0c48bac93a',
    short_commit: '2b49116',
    branch: 'migration/nextjs-node-parity',
    build_time: new Date().toISOString(),
    environment: process.env.NODE_ENV || 'production',
  });
}
