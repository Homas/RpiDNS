/*
 * (c) Vadim Pavlov 2020 - 2026
 * Smart Actions composable for RpiDNS
 * Provides smart block/allow logic that automatically determines
 * the correct operation based on domain feed membership
 */

import { useApi } from '@/composables/useApi'

/**
 * The four predefined local RPZ zones
 */
const LOCAL_FEEDS = [
  'allow.ioc2rpz.rpidns',
  'block.ioc2rpz.rpidns',
  'allow-ip.ioc2rpz.rpidns',
  'block-ip.ioc2rpz.rpidns'
]

const LOCAL_BLOCK_FEED = 'block.ioc2rpz.rpidns'

/**
 * Check if a feed name is the local block feed
 * @param {string} feedName - Feed name to check
 * @returns {boolean} True if the feed is the local block feed
 */
export function isLocalBlockFeed(feedName) {
  return feedName === LOCAL_BLOCK_FEED
}

/**
 * Check if a feed name is any of the four predefined local RPZ zones
 * @param {string} feedName - Feed name to check
 * @returns {boolean} True if the feed is a local feed
 */
export function isLocalFeed(feedName) {
  return LOCAL_FEEDS.includes(feedName)
}

/**
 * Composable for smart block/allow actions
 * Uses useApi internally for backend calls
 * @returns {Object} Smart action methods: smartBlock, smartAllow, isLocalBlockFeed, isLocalFeed
 */
export function useSmartActions() {
  const api = useApi()

  /**
   * Smart block action: checks if domain is in the allow list and removes it,
   * otherwise signals to add the domain to the block list.
   *
   * @param {string} domain - Domain name to block
   * @returns {Promise<Object>} Result object:
   *   - { action: 'removed', list: 'whitelist' } if domain was removed from allow list
   *   - { action: 'add-ioc', type: 'bl' } if domain should be added to block list
   *   - { action: 'error', error: string } if an API error occurred
   */
  async function smartBlock(domain) {
    try {
      const response = await api.get({ req: 'whitelist', sortBy: 'ioc', sortDesc: false })
      const entries = response.data || []
      const match = entries.find(entry => entry.ioc === domain)

      if (match) {
        await api.del({ req: 'whitelist', id: match.rowid })
        return { action: 'removed', list: 'whitelist' }
      }

      return { action: 'add-ioc', type: 'bl' }
    } catch (error) {
      return { action: 'error', error: error.message || 'Failed to perform smart block action' }
    }
  }

  /**
   * Smart allow action: if the blocking feed is a local block feed, removes the
   * domain from the block list; otherwise signals to add the domain to the allow list.
   *
   * @param {string} domain - Domain name to allow
   * @param {string} feedName - The feed that blocked the domain
   * @returns {Promise<Object>} Result object:
   *   - { action: 'removed', list: 'blacklist' } if domain was removed from block list
   *   - { action: 'add-ioc', type: 'wl' } if domain should be added to allow list
   *   - { action: 'error', error: string } if an API error occurred
   */
  async function smartAllow(domain, feedName) {
    try {
      if (isLocalBlockFeed(feedName)) {
        const response = await api.get({ req: 'blacklist', sortBy: 'ioc', sortDesc: false })
        const entries = response.data || []
        const match = entries.find(entry => entry.ioc === domain)

        if (match) {
          await api.del({ req: 'blacklist', id: match.rowid })
          return { action: 'removed', list: 'blacklist' }
        }

        // Domain not found in block list (race condition / already removed)
        return { action: 'add-ioc', type: 'wl' }
      }

      // Third-party feed: add to allow list
      return { action: 'add-ioc', type: 'wl' }
    } catch (error) {
      return { action: 'error', error: error.message || 'Failed to perform smart allow action' }
    }
  }

  return { smartBlock, smartAllow, isLocalBlockFeed, isLocalFeed }
}

export default useSmartActions
