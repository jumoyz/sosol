import React from 'react';
import { View, Text, ScrollView, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

export default function WalletScreen() {
  const balance = 1250.75; // Example balance

  return (
    <ScrollView className="flex-1 bg-background">
      <View className="px-4 py-6">
        {/* Balance Card */}
        <View className="bg-primary rounded-2xl p-6 mb-6">
          <Text className="text-white text-lg mb-2">Total Balance</Text>
          <Text className="text-white text-4xl font-bold">${balance.toFixed(2)}</Text>
          <Text className="text-white opacity-90 mt-2">SOSOL Wallet</Text>
        </View>

        {/* Quick Actions */}
        <View className="flex-row justify-between mb-6">
          <TouchableOpacity className="items-center">
            <View className="bg-secondary rounded-full p-3 mb-2">
              <Ionicons name="arrow-down" size={24} color="white" />
            </View>
            <Text className="text-text font-medium">Deposit</Text>
          </TouchableOpacity>
          
          <TouchableOpacity className="items-center">
            <View className="bg-secondary rounded-full p-3 mb-2">
              <Ionicons name="arrow-up" size={24} color="white" />
            </View>
            <Text className="text-text font-medium">Withdraw</Text>
          </TouchableOpacity>
          
          <TouchableOpacity className="items-center">
            <View className="bg-secondary rounded-full p-3 mb-2">
              <Ionicons name="swap-horizontal" size={24} color="white" />
            </View>
            <Text className="text-text font-medium">Transfer</Text>
          </TouchableOpacity>
        </View>

        {/* Recent Transactions */}
        <View className="bg-card rounded-xl p-4">
          <Text className="text-lg font-bold text-text mb-4">Recent Transactions</Text>
          {/* Transaction list would go here */}
          <Text className="text-gray-500 text-center py-4">No recent transactions</Text>
        </View>
      </View>
    </ScrollView>
  );
}