import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, TouchableOpacity, FlatList, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '@/services/api';

export default function SOLGroupsScreen() {
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchGroups();
  }, []);

  const fetchGroups = async () => {
    try {
      const response = await api.get('/sol-groups/');
      setGroups(response.data.groups || []);
    } catch (error) {
      Alert.alert('Error', 'Failed to load groups');
    } finally {
      setLoading(false);
    }
  };

  const renderGroupItem = ({ item }) => (
    <TouchableOpacity className="bg-card rounded-xl p-4 mb-3 border border-border">
      <View className="flex-row justify-between items-center">
        <View className="flex-1">
          <Text className="text-lg font-semibold text-text">{item.name}</Text>
          <Text className="text-gray-600">{item.members_count} members</Text>
          <Text className="text-gray-600">${item.total_savings} total savings</Text>
        </View>
        <Ionicons name="chevron-forward" size={20} color="#6B7280" />
      </View>
    </TouchableOpacity>
  );

  return (
    <View className="flex-1 bg-background">
      <ScrollView className="flex-1 px-4 py-6">
        <View className="flex-row justify-between items-center mb-6">
          <Text className="text-2xl font-bold text-text">SOL Groups</Text>
          <TouchableOpacity className="bg-primary rounded-lg px-4 py-2">
            <Text className="text-white font-semibold">Create Group</Text>
          </TouchableOpacity>
        </View>

        {loading ? (
          <Text className="text-center text-gray-500 py-4">Loading groups...</Text>
        ) : (
          <FlatList
            data={groups}
            renderItem={renderGroupItem}
            keyExtractor={(item) => item.id.toString()}
            ListEmptyComponent={
              <Text className="text-center text-gray-500 py-4">No groups found</Text>
            }
          />
        )}
      </ScrollView>
    </View>
  );
}